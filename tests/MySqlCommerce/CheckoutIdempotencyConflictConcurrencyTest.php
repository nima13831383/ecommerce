<?php

use App\Enums\AddressType;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CouponUsage;
use App\Models\CustomerNotification;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutInput;
use App\Services\Checkout\CheckoutService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('rejects a same-key different-fingerprint checkout race without duplicate state', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = checkoutConflictFixture();
    $idempotencyKey = 'mysql-checkout-conflict-'.uniqid();

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('checkout', [
        'user_id' => $fixture['user']->id,
        'cart_id' => $fixture['cart']->id,
        'shipping_address_id' => $fixture['address']->id,
        'billing_address_id' => null,
        'shipping_payment_type' => 'online',
        'idempotency_key' => $idempotencyKey,
        'worker_data' => [
            'A' => ['shipping_service' => 'pishtaz'],
            'B' => ['shipping_service' => 'vijeh'],
        ],
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('');

    $results = collect($run['results'])->pluck('json');
    expect($results->where('ok', true))->toHaveCount(1)
        ->and($results->where('ok', false))->toHaveCount(1)
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    $winner = $results->firstWhere('ok', true);
    $loser = $results->firstWhere('ok', false);
    expect($winner['result']['order_id'])->toBeInt()
        ->and($loser['exception']['class'])->toBe(DomainException::class)
        ->and($loser['exception']['message'])->toContain('different order request');

    $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
    $winningService = $winner['worker'] === 'A' ? 'pishtaz' : 'vijeh';
    expect($order->id)->toBe($winner['result']['order_id'])
        ->and($order->shipping_snapshot['service'])->toBe($winningService)
        ->and(Order::query()->where('idempotency_key', $idempotencyKey)->count())->toBe(1)
        ->and($order->items()->count())->toBe(1)
        ->and($order->inventoryReservations()->count())->toBe(1)
        ->and((int) $order->inventoryReservations()->sum('inventory_reservations.quantity'))->toBe(1)
        ->and(CouponUsage::query()->count())->toBe(0)
        ->and($order->statusHistories()->count())->toBe(1)
        ->and($order->statusHistories()->pluck('to_status')->all())->toBe([OrderStatus::Pending->value])
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->cart->status)->toBe('converted');

    $reservation = InventoryReservation::query()
        ->where('inventory_owner_type', Product::class)
        ->where('inventory_owner_id', $fixture['product']->id)
        ->where('status', InventoryReservationStatus::Active)
        ->firstOrFail();
    expect(InventoryReservation::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $fixture['product']->id)->count())->toBe(1)
        ->and((int) $reservation->quantity)->toBe(1)
        ->and((int) $fixture['product']->fresh()->stock_quantity)->toBe(10)
        ->and(app(InventoryService::class)->availableQuantity($fixture['product']->fresh()))->toBe(9)
        ->and(CustomerNotification::query()->where('order_id', $order->id)->count())->toBeLessThanOrEqual(1);
});

test('same-key same-fingerprint replay recovers the original order after cart conversion', function (): void {
    $fixture = checkoutConflictFixture();
    $service = app(CheckoutService::class);
    $input = checkoutConflictInput($fixture, 'sqlite-recovery-same-'.uniqid(), 'pishtaz');

    $first = $service->placeOrder($fixture['user'], $input);
    $retry = $service->placeOrder($fixture['user'], $input);

    expect($retry->order?->id)->toBe($first->order?->id)
        ->and(Order::query()->count())->toBe(1);
});

test('same-key different-fingerprint replay returns an idempotency conflict', function (): void {
    $fixture = checkoutConflictFixture();
    $service = app(CheckoutService::class);
    $key = 'sqlite-recovery-conflict-'.uniqid();

    $service->placeOrder($fixture['user'], checkoutConflictInput($fixture, $key, 'pishtaz'));

    expect(fn () => $service->placeOrder($fixture['user'], checkoutConflictInput($fixture, $key, 'vijeh')))
        ->toThrow(DomainException::class, 'different order request');
});

test('different-key replay against a converted cart remains an inactive-cart failure', function (): void {
    $fixture = checkoutConflictFixture();
    $service = app(CheckoutService::class);
    $service->placeOrder($fixture['user'], checkoutConflictInput($fixture, 'sqlite-recovery-converted-'.uniqid(), 'pishtaz'));

    expect(fn () => $service->placeOrder($fixture['user'], checkoutConflictInput($fixture, 'sqlite-recovery-new-'.uniqid(), 'pishtaz')))
        ->toThrow(DomainException::class, 'active Cart');
});

test('no existing order and an inactive cart remains an inactive-cart failure', function (): void {
    $fixture = checkoutConflictFixture();
    $fixture['cart']->forceFill(['status' => 'converted'])->save();

    expect(fn () => app(CheckoutService::class)->placeOrder(
        $fixture['user'],
        checkoutConflictInput($fixture, 'sqlite-recovery-no-order-'.uniqid(), 'pishtaz'),
    ))->toThrow(DomainException::class, 'active Cart');
});

/** @return array{user: User, address: Address, product: Product, cart: Cart} */
function checkoutConflictFixture(): array
{
    $settings = app(SettingsService::class);
    $settings->update('shipping.mode', 'calculator');
    $settings->update('shipping.origin_province_id', 2);
    $settings->update('shipping.origin_city_id', 4391);
    $settings->update('shipping.packages', [
        ['id' => 'default', 'name' => 'Default', 'capacity_volume' => 1000, 'max_weight' => 30_000, 'code' => 1, 'active' => true],
    ]);

    $user = User::factory()->create(['status' => 'active']);
    $address = $user->addresses()->create([
        'type' => AddressType::Both->value,
        'first_name' => 'Ali',
        'last_name' => 'Customer',
        'mobile' => '09120000000',
        'province_id' => 27,
        'city_id' => 6971,
        'postal_code' => '0123456789',
        'address_line' => 'Main Street 10',
        'plaque' => '10',
        'unit' => '2',
    ]);
    $product = Product::query()->create([
        'name' => 'Checkout conflict product',
        'slug' => 'checkout-conflict-product-'.uniqid(),
        'sku' => 'CHECKOUT-CONFLICT-'.uniqid(),
        'type' => 'simple',
        'price' => 100_000,
        'weight' => 1,
        'volume' => 10,
        'status' => 'published',
    ]);
    app(InventoryService::class)->setOnHand($product, 10, reason: 'Checkout conflict test setup');

    $cart = app(CartService::class)->getOrCreateForUser($user->id);
    app(CartService::class)->addItem($cart, $product, 1);

    return compact('user', 'address', 'product', 'cart');
}

function checkoutConflictInput(array $fixture, string $key, string $shippingService): CheckoutInput
{
    return new CheckoutInput(
        cartId: $fixture['cart']->id,
        shippingAddressId: $fixture['address']->id,
        billingAddressId: null,
        shippingService: $shippingService,
        shippingPaymentType: 'online',
        idempotencyKey: $key,
    );
}

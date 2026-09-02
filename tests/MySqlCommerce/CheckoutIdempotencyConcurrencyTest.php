<?php

use App\Enums\AddressType;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Models\CouponUsage;
use App\Models\CustomerNotification;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Settings\SettingsService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('converges same-key checkout requests on one order under two real MySQL workers', function (): void {
    $this->assertSafeMySqlTestDatabase();

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
        'name' => 'MySQL checkout race product',
        'slug' => 'mysql-checkout-race-product-'.uniqid(),
        'sku' => 'MYSQL-CHECKOUT-RACE-'.uniqid(),
        'type' => 'simple',
        'price' => 100_000,
        'weight' => 1,
        'volume' => 10,
        'status' => 'published',
    ]);
    app(InventoryService::class)->setOnHand($product, 10, reason: 'Checkout idempotency race setup');

    $cart = app(CartService::class)->getOrCreateForUser($user->id);
    app(CartService::class)->addItem($cart, $product, 1);
    $cart = $cart->fresh(['items']);
    $idempotencyKey = 'mysql-checkout-same-key-'.uniqid();

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('checkout', [
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'shipping_address_id' => $address->id,
        'billing_address_id' => null,
        'shipping_service' => 'pishtaz',
        'shipping_payment_type' => 'online',
        'idempotency_key' => $idempotencyKey,
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('');

    $results = collect($run['results'])->pluck('json');
    expect($results)->toHaveCount(2)
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0)
        ->and($results->every(fn (array $result): bool => $result['ok'] === true))->toBeTrue();

    $orderIds = $results->map(fn (array $result): int => (int) $result['result']['order_id'])->unique();
    expect($orderIds)->toHaveCount(1);

    $order = Order::query()->where('idempotency_key', $idempotencyKey)->firstOrFail();
    expect($order->id)->toBe($orderIds->first())
        ->and(Order::query()->where('idempotency_key', $idempotencyKey)->count())->toBe(1)
        ->and($order->items()->count())->toBe(1)
        ->and($order->inventoryReservations()->count())->toBe(1)
        ->and((int) $order->inventoryReservations()->sum('inventory_reservations.quantity'))->toBe(1)
        ->and(CouponUsage::query()->count())->toBe(0)
        ->and($order->statusHistories()->count())->toBe(1)
        ->and($order->statusHistories()->pluck('to_status')->all())->toBe([OrderStatus::Pending->value])
        ->and($order->status)->toBe(OrderStatus::Pending);

    $reservation = InventoryReservation::query()
        ->where('inventory_owner_type', Product::class)
        ->where('inventory_owner_id', $product->id)
        ->where('status', InventoryReservationStatus::Active)
        ->firstOrFail();
    expect(InventoryReservation::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(1)
        ->and((int) $reservation->quantity)->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and(app(InventoryService::class)->availableQuantity($product->fresh()))->toBe(9);

    $notificationCount = CustomerNotification::query()->where('order_id', $order->id)->count();
    expect($notificationCount)->toBeLessThanOrEqual(1);

    expect($cart->fresh()->status)->toBe('converted');
});

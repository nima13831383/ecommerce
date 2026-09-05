<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\Data\PaymentVerificationResult;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Storefront\StorefrontPaymentGateway;
use App\Support\PersianNumber;
use Tests\Support\Payments\FakePaymentGateway;

beforeEach(function (): void {
    $this->fakeGateway = new FakePaymentGateway;
    $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([$this->fakeGateway]));
});

function storefrontPaymentProduct(string $suffix = 'default', int $price = 120_000, int $stock = 5): Product
{
    $product = Product::query()->create([
        'name' => "Payment storefront {$suffix}",
        'slug' => "payment-storefront-{$suffix}",
        'sku' => "PAYMENT-STOREFRONT-{$suffix}",
        'type' => 'simple',
        'price' => $price,
        'status' => 'published',
        'stock_quantity' => $stock,
        'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
    ]);

    app(InventoryService::class)->setOnHand($product, $stock, reason: 'Payment storefront test setup');

    return $product;
}

function storefrontPaymentOrder(Product $product, string $suffix = 'order'): Order
{
    return app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 1]],
        ['customer_name' => 'Payment Customer', 'customer_mobile' => '09120000000'],
    );
}

test('guest cannot initiate payment and owner receives a provider-neutral handoff', function (): void {
    $order = storefrontPaymentOrder(storefrontPaymentProduct('guest'));

    $this->post(route('storefront.payment.initiate', ['order' => $order->order_number]))
        ->assertRedirect(route('login'));

    $user = User::factory()->create();
    $owned = storefrontPaymentOrder(storefrontPaymentProduct('owned'));
    $owned->forceFill(['user_id' => $user->id])->save();

    $this->actingAs($user)->get(route('storefront.checkout.success', ['order' => $owned->id]))
        ->assertOk()
        ->assertSee('ادامه به پرداخت');

    $response = $this->actingAs($user)->post(route('storefront.payment.initiate', ['order' => $owned->order_number]), [
        'amount' => 1,
        'gateway' => 'attacker-gateway',
    ]);

    $payment = Payment::query()->where('order_id', $owned->id)->sole();
    $response->assertRedirect(route('storefront.payment.return', ['payment' => $payment->id]));
    expect($payment->amount)->toBe($owned->grand_total)
        ->and($payment->status)->toBe(PaymentStatus::Processing);
});

test('checkout success exposes a csrf protected payment form when a gateway is available', function (): void {
    $user = User::factory()->create();
    $order = storefrontPaymentOrder(storefrontPaymentProduct('button-contract'));
    $order->forceFill(['user_id' => $user->id])->save();

    $this->actingAs($user)
        ->get(route('storefront.checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertSee('method="post"', false)
        ->assertSee(route('storefront.payment.initiate', ['order' => $order->order_number]), false)
        ->assertSee('name="_token"', false)
        ->assertSee('ادامه به پرداخت');
});

test('checkout success visibly explains an unavailable configured gateway', function (): void {
    $user = User::factory()->create();
    $order = storefrontPaymentOrder(storefrontPaymentProduct('unavailable-gateway'));
    $order->forceFill(['user_id' => $user->id])->save();

    $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([]));

    $this->actingAs($user)
        ->get(route('storefront.checkout.success', ['order' => $order->id]))
        ->assertOk()
        ->assertSee('درگاه پرداخت در حال حاضر در دسترس نیست.')
        ->assertDontSee('ادامه به پرداخت');
});

test('fake payment return verifies once, commits inventory, and exposes safe result data', function (): void {
    $user = User::factory()->create();
    $product = storefrontPaymentProduct('success', 80_000, 2);
    $order = storefrontPaymentOrder($product);
    $order->forceFill(['user_id' => $user->id])->save();
    $beforeTransactions = InventoryTransaction::query()->count();

    $this->actingAs($user)->post(route('storefront.payment.initiate', ['order' => $order->order_number]))
        ->assertRedirect();
    $payment = Payment::query()->where('order_id', $order->id)->sole();

    $result = $this->actingAs($user)->get(route('storefront.payment.return', ['payment' => $payment->id]));
    $result->assertOk()
        ->assertSee('پرداخت با موفقیت تأیید شد')
        ->assertSee($order->order_number)
        ->assertSee(PersianNumber::money($order->grand_total))
        ->assertDontSee('gateway_response')
        ->assertDontSee('initiation_fingerprint');

    $payment = $payment->fresh();
    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($product->fresh()->stock_quantity)->toBe(1)
        ->and($payment->transactions()->where('type', 'verify')->count())->toBe(1)
        ->and(InventoryTransaction::query()->count())->toBe($beforeTransactions + 1);

    $this->actingAs($user)->get(route('storefront.payment.return', ['payment' => $payment->id]))->assertOk();
    expect($payment->fresh()->transactions()->where('type', 'verify')->count())->toBe(1)
        ->and($product->fresh()->stock_quantity)->toBe(1);
});

test('failed verification leaves the reservation active and allows safe retry state', function (): void {
    $user = User::factory()->create();
    $product = storefrontPaymentProduct('failure');
    $order = storefrontPaymentOrder($product);
    $order->forceFill(['user_id' => $user->id])->save();

    $this->actingAs($user)->post(route('storefront.payment.initiate', ['order' => $order->order_number]));
    $payment = Payment::query()->where('order_id', $order->id)->sole();
    $this->fakeGateway->verificationResult = new PaymentVerificationResult(false, failureReason: 'declined');

    $this->actingAs($user)->get(route('storefront.payment.return', ['payment' => $payment->id]))
        ->assertOk()
        ->assertSee('پرداخت ناموفق بود');

    expect($payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Unpaid)
        ->and($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and($order->items()->sole()->inventoryReservation->status->value)->toBe('active');

    $this->fakeGateway->verificationResult = null;
    $this->actingAs($user)->post(route('storefront.payment.initiate', ['order' => $order->order_number]))->assertRedirect();
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(2);
});

test('payment result and initiation are ownership protected and ineligible orders fail safely', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = storefrontPaymentOrder(storefrontPaymentProduct('idor'));
    $order->forceFill(['user_id' => $owner->id])->save();

    $this->actingAs($other)->post(route('storefront.payment.initiate', ['order' => $order->order_number]))->assertNotFound();

    $this->actingAs($owner)->post(route('storefront.payment.initiate', ['order' => $order->order_number]))->assertRedirect();
    $payment = Payment::query()->where('order_id', $order->id)->sole();
    $this->actingAs($other)->get(route('storefront.payment.result', ['payment' => $payment->id]))->assertNotFound();

    $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
    $this->actingAs($owner)->post(route('storefront.payment.initiate', ['order' => $order->order_number]))
        ->assertSessionHasErrors('payment');
    expect(Payment::query()->where('order_id', $order->id)->count())->toBe(1);
});

test('fake gateway is unavailable outside local and testing environments', function (): void {
    $service = app(StorefrontPaymentGateway::class);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect($service->alias())->toBeNull();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

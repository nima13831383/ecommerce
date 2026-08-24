<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Orders\OrderService;
use App\Services\Payments\Data\PaymentVerificationResult;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\PaymentService;
use Tests\Support\Payments\FakePaymentGateway;

function paymentProduct(array $overrides = []): Product
{
    $product = Product::query()->create(array_replace([
        'name' => 'Payment product',
        'slug' => 'payment-product-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'PAY-'.fake()->unique()->numerify('####'),
        'price' => 12_345,
    ], $overrides));
    $product->forceFill(['stock_quantity' => 20, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function paymentOrderDetails(): array
{
    return ['customer_name' => 'Payment Customer', 'customer_mobile' => '09120000000'];
}

function fakePaymentService(?FakePaymentGateway $gateway = null): array
{
    $gateway ??= new FakePaymentGateway;
    $service = new PaymentService(new PaymentGatewayRegistry([$gateway]), app(OrderService::class));

    return [$service, $gateway];
}

test('it initiates a payment from the authoritative order amount without committing stock', function (): void {
    $product = paymentProduct(['price' => 10_000]);
    $order = app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 2]], paymentOrderDetails());
    [$service] = fakePaymentService();
    $payment = $service->initiate($order, 'fake', 'payment-init-1');

    expect($payment->amount)->toBe($order->grand_total)
        ->and($payment->status)->toBe(PaymentStatus::Processing)
        ->and($payment->authority)->toBe("fake-init-{$payment->id}")
        ->and($product->fresh()->stock_quantity)->toBe(20)
        ->and($order->items()->sole()->inventoryReservation->status)->toBe(InventoryReservationStatus::Active);
});

test('it handles initiation idempotently and safely records initiation failures', function (): void {
    $order = app(OrderService::class)->create([['product_id' => paymentProduct()->id, 'quantity' => 1]], paymentOrderDetails());
    [$service] = fakePaymentService();
    $first = $service->initiate($order, 'fake', 'same-key');
    $second = $service->initiate($order, 'fake', 'same-key');

    expect($second->id)->toBe($first->id)->and(Payment::count())->toBe(1);

    $failedOrder = app(OrderService::class)->create([['product_id' => paymentProduct()->id, 'quantity' => 1]], paymentOrderDetails());
    [$failedService, $gateway] = fakePaymentService();
    $gateway->initiationSucceeds = false;
    $failed = $failedService->initiate($failedOrder, 'fake', 'failed-init');

    expect($failed->status)->toBe(PaymentStatus::Failed)
        ->and($failedOrder->items()->sole()->inventoryReservation->status)->toBe(InventoryReservationStatus::Active);
});

test('only gateway verification can complete payment and commit inventory once', function (): void {
    $product = paymentProduct();
    $order = app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 2]], paymentOrderDetails());
    [$service] = fakePaymentService();
    $payment = $service->initiate($order, 'fake', 'verify-success');

    expect($product->fresh()->stock_quantity)->toBe(20);

    $payment = $service->verify($payment);
    $again = $service->verify($payment);

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($again->id)->toBe($payment->id)
        ->and($payment->reconciliation_required)->toBeFalse()
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($product->fresh()->stock_quantity)->toBe(18)
        ->and(InventoryTransaction::where('operation', 'reservation_commit')->count())->toBe(1);
});

test('verification failure preserves active inventory for another payment attempt', function (): void {
    $product = paymentProduct();
    $order = app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 1]], paymentOrderDetails());
    [$service, $gateway] = fakePaymentService();
    $payment = $service->initiate($order, 'fake', 'verify-failure');
    $gateway->verificationResult = new PaymentVerificationResult(false, failureReason: 'Fake verification failure.');
    $failed = $service->verify($payment);

    expect($failed->status)->toBe(PaymentStatus::Failed)
        ->and($order->fresh()->payment_status)->toBe(OrderPaymentStatus::Unpaid)
        ->and($product->fresh()->stock_quantity)->toBe(20)
        ->and($order->items()->sole()->inventoryReservation->status)->toBe(InventoryReservationStatus::Active);
});

test('late, mismatched, expired, and duplicate successful payments require reconciliation without stock mutation', function (): void {
    $product = paymentProduct();
    $order = app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 1]], paymentOrderDetails());
    [$service, $gateway] = fakePaymentService();
    $payment = $service->initiate($order, 'fake', 'mismatch');
    $gateway->verificationResult = new PaymentVerificationResult(true, 'ref-mismatch', $payment->amount + 1, $payment->currency);
    expect($service->verify($payment)->reconciliation_required)->toBeTrue()->and($product->fresh()->stock_quantity)->toBe(20);

    $expiredOrder = app(OrderService::class)->create([['product_id' => paymentProduct()->id, 'quantity' => 1]], paymentOrderDetails());
    [$expiredService] = fakePaymentService();
    $expiredPayment = $expiredService->initiate($expiredOrder, 'fake', 'expired');
    $expiredOrder->items()->sole()->inventoryReservation->update(['expires_at' => now()->subMinute()]);
    expect($expiredService->verify($expiredPayment)->reconciliation_required)->toBeTrue();

    $paidOrder = app(OrderService::class)->create([['product_id' => paymentProduct()->id, 'quantity' => 1]], paymentOrderDetails());
    [$paidService] = fakePaymentService();
    $first = $paidService->initiate($paidOrder, 'fake', 'first-paid');
    $second = $paidService->initiate($paidOrder, 'fake', 'second-paid');
    $paidService->verify($first);
    expect($paidService->verify($second)->reconciliation_required)->toBeTrue();
});

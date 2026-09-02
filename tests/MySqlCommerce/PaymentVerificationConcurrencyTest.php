<?php

use App\Enums\InventoryOperation;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\CustomerNotification;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\PaymentService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;
use Tests\Support\Payments\FakePaymentGateway;

uses(EnsuresMySqlTestDatabase::class);

it('verifies one successful payment exactly once under two real MySQL workers', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = paymentVerificationFixture();

    expect($fixture['payment']->status)->toBe(PaymentStatus::Processing)
        ->and($fixture['order']->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($fixture['order']->payment_status)->toBe(OrderPaymentStatus::Unpaid)
        ->and($fixture['product']->stock_quantity)->toBe(5)
        ->and($fixture['reservation']->status)->toBe(InventoryReservationStatus::Active)
        ->and((int) $fixture['reservation']->quantity)->toBe(1)
        ->and(InventoryTransaction::query()->where('operation', InventoryOperation::ReservationCommit)->count())->toBe(0);

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('payment_verify', [
        'payment_id' => $fixture['payment']->id,
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('');

    $results = collect($run['results'])->pluck('json');
    expect($results->every(fn (array $result): bool => $result['ok'] === true))->toBeTrue()
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0)
        ->and($results->pluck('result.payment_id')->unique()->values()->all())->toBe([$fixture['payment']->id])
        ->and($results->pluck('result.payment_status')->unique()->values()->all())->toBe([PaymentStatus::Paid->value])
        ->and($results->pluck('result.order_status')->unique()->values()->all())->toBe([OrderStatus::Processing->value]);

    $payment = $fixture['payment']->fresh();
    $order = $fixture['order']->fresh();
    $reservation = $fixture['reservation']->fresh();
    $commitTransactions = InventoryTransaction::query()
        ->where('operation', InventoryOperation::ReservationCommit)
        ->where('reference_type', 'inventory_reservation')
        ->where('reference_id', (string) $reservation->id)
        ->get();

    expect(Payment::query()->whereKey($payment->id)->count())->toBe(1)
        ->and($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->amount)->toBe($order->grand_total)
        ->and($payment->paid_amount)->toBe($order->grand_total)
        ->and($payment->reference_id)->toBe("fake-reference-{$payment->id}")
        ->and($payment->verified_at)->not->toBeNull()
        ->and(PaymentTransaction::query()->where('payment_id', $payment->id)->where('type', 'verify')->count())->toBe(1)
        ->and($order->status)->toBe(OrderStatus::Processing)
        ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
        ->and($order->statusHistories()->where('type', 'payment_status')->where('to_status', 'paid')->count())->toBe(1)
        ->and($order->statusHistories()->where('type', 'status')->where('to_status', OrderStatus::Processing->value)->count())->toBe(1)
        ->and($order->statusHistories()->count())->toBe(4)
        ->and($reservation->status)->toBe(InventoryReservationStatus::Committed)
        ->and($commitTransactions)->toHaveCount(1)
        ->and((int) $commitTransactions->sole()->quantity_delta)->toBe(-1)
        ->and($fixture['product']->fresh()->stock_quantity)->toBe(4)
        ->and(app(InventoryService::class)->availableQuantity($fixture['product']->fresh()))->toBe(4)
        ->and(InventoryReservation::query()->where('inventory_owner_type', Product::class)->where('inventory_owner_id', $fixture['product']->id)->where('status', InventoryReservationStatus::Active)->count())->toBe(0)
        ->and(CustomerNotification::query()->where('order_id', $order->id)->where('type', 'payment_succeeded')->count())->toBe(1);
});

it('replays successful payment verification without repeating side effects', function (): void {
    $fixture = paymentVerificationFixture();
    DB::commit();

    $service = new PaymentService(
        new PaymentGatewayRegistry([new FakePaymentGateway]),
        app(OrderService::class),
    );
    $first = $service->verify($fixture['payment']);
    $second = $service->verify($fixture['payment']);

    expect($first->status)->toBe(PaymentStatus::Paid)
        ->and($second->status)->toBe(PaymentStatus::Paid)
        ->and($fixture['product']->fresh()->stock_quantity)->toBe(4)
        ->and(InventoryTransaction::query()->where('operation', InventoryOperation::ReservationCommit)->count())->toBe(1)
        ->and(PaymentTransaction::query()->where('payment_id', $fixture['payment']->id)->where('type', 'verify')->count())->toBe(1)
        ->and($fixture['order']->fresh()->status)->toBe(OrderStatus::Processing)
        ->and($fixture['order']->statusHistories()->where('to_status', OrderStatus::Processing->value)->count())->toBe(1)
        ->and(CustomerNotification::query()->where('order_id', $fixture['order']->id)->where('type', 'payment_succeeded')->count())->toBe(1);
});

/** @return array{product: Product, order: Order, payment: Payment, reservation: InventoryReservation} */
function paymentVerificationFixture(): array
{
    $product = Product::query()->create([
        'name' => 'Payment verification race product',
        'slug' => 'payment-verification-race-'.uniqid(),
        'sku' => 'PAYMENT-VERIFICATION-RACE-'.uniqid(),
        'type' => 'simple',
        'price' => 100_000,
        'weight' => 1,
        'volume' => 10,
        'status' => 'published',
    ]);
    $product->forceFill(['stock_quantity' => 5, 'stock_status' => 'in_stock'])->save();

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 1],
    ], [
        'customer_name' => 'Payment Customer',
        'customer_mobile' => '09120000000',
        'user_id' => null,
    ]);
    $reservation = $order->items()->sole()->inventoryReservation;
    $paymentService = new PaymentService(
        new PaymentGatewayRegistry([new FakePaymentGateway]),
        app(OrderService::class),
    );
    $payment = $paymentService->initiate($order, 'fake', 'payment-verification-race-'.uniqid());
    $order = $order->fresh(['items.inventoryReservation']);
    $reservation = $order->items()->sole()->inventoryReservation;

    return compact('product', 'order', 'payment', 'reservation');
}

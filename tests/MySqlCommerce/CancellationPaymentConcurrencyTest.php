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

it('keeps cancellation and payment verification consistent under MySQL contention', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = cancellationPaymentFixture();

    expect($fixture['order']->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($fixture['order']->payment_status)->toBe(OrderPaymentStatus::Unpaid)
        ->and($fixture['payment']->status)->toBe(PaymentStatus::Processing)
        ->and($fixture['reservation']->status)->toBe(InventoryReservationStatus::Active)
        ->and($fixture['product']->stock_quantity)->toBe(5)
        ->and((int) $fixture['reservation']->quantity)->toBe(1)
        ->and(InventoryTransaction::query()->where('operation', InventoryOperation::ReservationCommit)->count())->toBe(0);

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('payment_verify', [
        'payment_id' => $fixture['payment']->id,
        'worker_data' => [
            'A' => [
                'operation' => 'payment_verify',
            ],
            'B' => [
                'operation' => 'order_cancel',
                'order_id' => $fixture['order']->id,
                'reason' => 'Concurrent cancellation test.',
            ],
        ],
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('')
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    $paymentResult = $run['results']['A']['json'];
    $cancelResult = $run['results']['B']['json'];
    $order = $fixture['order']->fresh();
    $payment = $fixture['payment']->fresh();
    $reservation = $fixture['reservation']->fresh();
    $commitTransactions = InventoryTransaction::query()
        ->where('operation', InventoryOperation::ReservationCommit)
        ->where('reference_type', 'inventory_reservation')
        ->where('reference_id', (string) $reservation->id)
        ->get();
    $histories = $order->statusHistories()->orderBy('id')->get()->map(fn ($history): array => [
        $history->from_status,
        $history->to_status,
        $history->type,
    ])->all();
    $paymentNotifications = CustomerNotification::query()
        ->where('order_id', $order->id)
        ->where('type', 'payment_succeeded')
        ->get();
    $cancelNotifications = CustomerNotification::query()
        ->where('order_id', $order->id)
        ->where('type', 'order_cancelled')
        ->get();

    expect($paymentResult['ok'])->toBeTrue()
        ->and($paymentResult['result']['payment_id'])->toBe($payment->id)
        ->and($paymentResult['result']['payment_status'])->toBe(PaymentStatus::Paid->value)
        ->and($cancelResult['worker'])->toBe('B');

    if (! $cancelResult['ok']) {
        expect($cancelResult['exception']['class'])->not->toContain('QueryException')
            ->and($cancelResult['exception']['class'])->not->toContain('Deadlock')
            ->and($cancelResult['exception']['class'])->not->toContain('LockWaitTimeout');
    }

    if ($order->status === OrderStatus::Processing) {
        expect($cancelResult['ok'])->toBeFalse()
            ->and($payment->reconciliation_required)->toBeFalse()
            ->and($payment->status)->toBe(PaymentStatus::Paid)
            ->and($order->payment_status)->toBe(OrderPaymentStatus::Paid)
            ->and($reservation->status)->toBe(InventoryReservationStatus::Committed)
            ->and($fixture['product']->fresh()->stock_quantity)->toBe(4)
            ->and($commitTransactions)->toHaveCount(1)
            ->and($histories)->toBe([
                [null, OrderStatus::Pending->value, 'status'],
                [OrderStatus::Pending->value, OrderStatus::AwaitingPayment->value, 'status'],
                ['unpaid', 'paid', 'payment_status'],
                [OrderStatus::AwaitingPayment->value, OrderStatus::Processing->value, 'status'],
            ])
            ->and($paymentNotifications)->toHaveCount(1)
            ->and($cancelNotifications)->toHaveCount(0);
    } else {
        expect($order->status)->toBe(OrderStatus::Cancelled)
            ->and($cancelResult['ok'])->toBeTrue()
            ->and($cancelResult['result']['order_status'])->toBe(OrderStatus::Cancelled->value)
            ->and($payment->reconciliation_required)->toBeTrue()
            ->and($payment->status)->toBe(PaymentStatus::Paid)
            ->and($order->payment_status)->toBe(OrderPaymentStatus::Unpaid)
            ->and($reservation->status)->toBe(InventoryReservationStatus::Released)
            ->and($fixture['product']->fresh()->stock_quantity)->toBe(5)
            ->and($commitTransactions)->toHaveCount(0)
            ->and($histories)->toBe([
                [null, OrderStatus::Pending->value, 'status'],
                [OrderStatus::Pending->value, OrderStatus::AwaitingPayment->value, 'status'],
                [OrderStatus::AwaitingPayment->value, OrderStatus::Cancelled->value, 'status'],
            ])
            ->and($paymentNotifications)->toHaveCount(0)
            ->and($cancelNotifications)->toHaveCount(1);
    }

    expect(Order::query()->whereKey($order->id)->count())->toBe(1)
        ->and(Payment::query()->whereKey($payment->id)->count())->toBe(1)
        ->and($payment->amount)->toBe($order->grand_total)
        ->and($payment->paid_amount)->toBe($order->grand_total)
        ->and($payment->reference_id)->toBe("fake-reference-{$payment->id}")
        ->and(PaymentTransaction::query()->where('payment_id', $payment->id)->where('type', 'request')->count())->toBe(1)
        ->and(PaymentTransaction::query()->where('payment_id', $payment->id)->where('type', 'verify')->count())->toBe(1)
        ->and($order->items()->count())->toBe(1)
        ->and(InventoryReservation::query()->whereHas('orderItem', fn ($query) => $query->where('order_id', $order->id))->count())->toBe(1)
        ->and(app(InventoryService::class)->availableQuantity($fixture['product']->fresh()))->toBe($fixture['product']->fresh()->stock_quantity - ($reservation->status === InventoryReservationStatus::Committed ? 1 : 0));

});

/** @return array{product: Product, order: Order, payment: Payment, reservation: InventoryReservation} */
function cancellationPaymentFixture(): array
{
    $product = Product::query()->create([
        'name' => 'Cancellation payment race product',
        'slug' => 'cancellation-payment-race-'.uniqid(),
        'sku' => 'CANCELLATION-PAYMENT-RACE-'.uniqid(),
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
        'customer_name' => 'Cancellation Customer',
        'customer_mobile' => '09120000000',
        'user_id' => null,
    ]);
    $payment = (new PaymentService(
        new PaymentGatewayRegistry([new FakePaymentGateway]),
        app(OrderService::class),
    ))->initiate($order, 'fake', 'cancellation-payment-race-'.uniqid());
    $order = $order->fresh(['items.inventoryReservation']);

    return [
        'product' => $product,
        'order' => $order,
        'payment' => $payment,
        'reservation' => $order->items()->sole()->inventoryReservation,
    ];
}

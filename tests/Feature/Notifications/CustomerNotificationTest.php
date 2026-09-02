<?php

use App\Contracts\Notifications\NotificationChannelInterface;
use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Jobs\Notifications\DeliverCustomerNotification;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Notifications\Channels\DevelopmentNotificationChannel;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Notifications\Data\NotificationDeliveryResult;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\PaymentService;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Payments\FakePaymentGateway;

function notificationProduct(string $suffix = ''): Product
{
    $product = Product::query()->create([
        'name' => 'Notification product',
        'slug' => 'notification-product-'.Str::lower(Str::random(8)).$suffix,
        'type' => 'simple',
        'sku' => 'NOTIFY-'.Str::upper(Str::random(8)),
        'price' => 15_000,
    ]);
    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function notificationOrder(array $details = []): Order
{
    return app(OrderService::class)->create(
        [['product_id' => notificationProduct()->id, 'quantity' => 1]],
        array_replace(['customer_name' => 'Notification Customer', 'customer_mobile' => '09120000000'], $details),
    );
}

test('a committed order creates one queued intent and keeps the historical recipient snapshot', function (): void {
    $user = User::factory()->create(['name' => 'Original Name', 'email' => 'original@example.test', 'mobile' => '09121111111']);
    $order = notificationOrder([
        'user_id' => $user->id,
        'customer_name' => $user->name,
        'customer_mobile' => $user->mobile,
        'customer_email' => $user->email,
        'idempotency_key' => 'notification-order-replay',
    ]);

    app(OrderService::class)->create(
        [['product_id' => $order->items->sole()->product_id, 'quantity' => 1]],
        [
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_mobile' => $user->mobile,
            'customer_email' => $user->email,
            'idempotency_key' => 'notification-order-replay',
        ],
    );

    $user->update(['name' => 'Changed Name', 'email' => 'changed@example.test']);
    $notification = CustomerNotification::query()->where('type', CustomerNotificationType::OrderPlaced)->sole();

    expect($notification->status)->toBe(CustomerNotificationStatus::Queued)
        ->and($notification->idempotency_key)->toBe("order:{$order->id}:placed")
        ->and($notification->recipient_snapshot)->toMatchArray([
            'name' => 'Original Name', 'email' => 'original@example.test', 'mobile' => '09121111111',
        ])
        ->and(CustomerNotification::query()->where('type', CustomerNotificationType::OrderPlaced)->count())->toBe(1);
});

test('an order rollback emits no lifecycle notification', function (): void {
    $valid = notificationProduct('valid');
    $invalid = notificationProduct('invalid');
    $invalid->forceFill(['type' => 'unsupported'])->save();

    expect(fn () => app(OrderService::class)->create([
        ['product_id' => $valid->id, 'quantity' => 1],
        ['product_id' => $invalid->id, 'quantity' => 1],
    ], ['customer_name' => 'Rollback', 'customer_mobile' => '09120000000']))
        ->toThrow(DomainException::class);

    expect(CustomerNotification::query()->count())->toBe(0);
});

test('payment success creates one intent only for a genuine successful verification', function (): void {
    $order = notificationOrder();
    $payments = new PaymentService(new PaymentGatewayRegistry([new FakePaymentGateway]), app(OrderService::class));
    $payment = $payments->initiate($order, 'fake', 'notification-payment');

    $payments->verify($payment);
    $payments->verify($payment);

    expect(CustomerNotification::query()->where('type', CustomerNotificationType::PaymentSucceeded)->count())->toBe(1);
});

test('shipment lifecycle events are emitted once per real transition', function (): void {
    $order = notificationOrder();
    $orders = app(OrderService::class);
    $orders->commitInventoryForOrder($order);
    $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
    $orders->transitionStatus($order, OrderStatus::AwaitingPayment);
    $orders->transitionStatus($order, OrderStatus::Processing);

    $shipments = app(ShipmentService::class);
    $shipment = $shipments->ensure($order);
    $shipments->transition($shipment, ShipmentStatus::Ready);
    $shipments->transition($shipment, ShipmentStatus::Shipped);
    $shipments->transition($shipment, ShipmentStatus::Shipped);
    $shipments->transition($shipment, ShipmentStatus::Delivered);

    expect(CustomerNotification::query()->where('type', CustomerNotificationType::ShipmentReady)->count())->toBe(1)
        ->and(CustomerNotification::query()->where('type', CustomerNotificationType::ShipmentShipped)->count())->toBe(1)
        ->and(CustomerNotification::query()->where('type', CustomerNotificationType::ShipmentDelivered)->count())->toBe(1);
});

test('lifecycle notification creation is queued and failed intents can be retried without duplication', function (): void {
    Queue::fake();
    $order = notificationOrder();

    Queue::assertPushed(CallQueuedListener::class);
    expect(CustomerNotification::query()->count())->toBe(0);

    $notification = CustomerNotification::query()->create([
        'order_id' => $order->id,
        'type' => CustomerNotificationType::OrderCancelled,
        'channel' => 'development',
        'recipient_snapshot' => ['mobile' => $order->customer_mobile],
        'payload_snapshot' => ['order_number' => $order->order_number],
        'status' => CustomerNotificationStatus::Failed,
        'idempotency_key' => "order:{$order->id}:cancelled",
        'attempts' => 1,
        'last_error' => 'temporary failure',
        'failed_at' => now(),
    ]);

    $retried = app(CustomerNotificationService::class)->retry($notification);

    Queue::assertPushed(DeliverCustomerNotification::class);

    expect($retried->id)->toBe($notification->id)
        ->and(CustomerNotification::query()->where('idempotency_key', $notification->idempotency_key)->count())->toBe(1);
});

test('a development delivery failure is isolated from the committed order', function (): void {
    app()->bind(DevelopmentNotificationChannel::class, fn (): object => new class implements NotificationChannelInterface
    {
        public function send(CustomerNotification $notification): NotificationDeliveryResult
        {
            throw new RuntimeException('development transport unavailable');
        }
    });

    $order = notificationOrder();
    $notification = CustomerNotification::query()->where('order_id', $order->id)->sole();

    expect($order->exists)->toBeTrue()
        ->and($notification->status)->toBe(CustomerNotificationStatus::Failed)
        ->and($notification->last_error)->toBe('development transport unavailable');
});

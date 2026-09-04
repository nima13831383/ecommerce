<?php

use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;
use App\Events\CustomerLifecycle\OrderPlaced;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Notifications\CustomerNotificationService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('creates one order-placed notification intent under two real MySQL workers', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = notificationIntentFixture();

    expect(CustomerNotification::query()->where('idempotency_key', $fixture['key'])->count())->toBe(0)
        ->and($fixture['order']->exists)->toBeTrue()
        ->and($fixture['key'])->toBe("order:{$fixture['order']->id}:placed");

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('notification_intent', [
        'order_id' => $fixture['order']->id,
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('')
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    $results = collect($run['results'])->pluck('json');

    expect($results->every(fn (array $result): bool => $result['ok'] === true))->toBeTrue()
        ->and($results->pluck('result.notification_id')->unique()->values())->toHaveCount(1)
        ->and($results->pluck('result.notification_type')->unique()->values()->all())->toBe([CustomerNotificationType::OrderPlaced->value])
        ->and($results->pluck('result.idempotency_key')->unique()->values()->all())->toBe([$fixture['key']]);

    $notification = CustomerNotification::query()
        ->where('idempotency_key', $fixture['key'])
        ->sole();

    expect(CustomerNotification::query()->where('idempotency_key', $fixture['key'])->count())->toBe(1)
        ->and($results->pluck('result.notification_id')->unique()->values()->all())->toBe([$notification->id])
        ->and($notification->type)->toBe(CustomerNotificationType::OrderPlaced)
        ->and($notification->status)->toBe(CustomerNotificationStatus::Queued)
        ->and($notification->order_id)->toBe($fixture['order']->id)
        ->and($notification->recipient_snapshot)->toBe($fixture['recipientSnapshot'])
        ->and($notification->attempts)->toBe(1)
        ->and($results->pluck('result.notification_status')->unique()->values()->all())->toBe([$notification->status->value])
        ->and($results->pluck('result.attempts')->every(
            fn (int $attempts): bool => $attempts >= 0 && $attempts <= $notification->attempts,
        ))->toBeTrue();
});

it('reuses one order-placed notification intent sequentially', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = notificationIntentFixture();
    $service = app(CustomerNotificationService::class);

    $first = createOrderPlacedNotificationIntent($service, $fixture['order']);
    $second = createOrderPlacedNotificationIntent($service, $fixture['order']);

    expect($second->id)->toBe($first->id)
        ->and(CustomerNotification::query()->where('idempotency_key', $fixture['key'])->count())->toBe(1)
        ->and($first->status)->toBe(CustomerNotificationStatus::Queued);
});

/** @return array{user: User, product: Product, order: Order, key: string, recipientSnapshot: array{name: string, mobile: string, email: string}} */
function notificationIntentFixture(): array
{
    $user = User::factory()->create([
        'name' => 'Notification Intent Customer',
        'email' => 'notification-intent@example.test',
        'mobile' => '09121111111',
    ]);
    $product = Product::query()->create([
        'name' => 'Notification intent race product',
        'slug' => 'notification-intent-race-'.Str::lower(Str::random(12)),
        'sku' => 'NOTIFICATION-INTENT-RACE-'.Str::upper(Str::random(12)),
        'type' => 'simple',
        'price' => 100_000,
        'status' => 'published',
    ]);
    $product->forceFill(['stock_quantity' => 5, 'stock_status' => 'in_stock'])->save();

    Event::fake([OrderPlaced::class]);

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 1],
    ], [
        'user_id' => $user->id,
        'customer_name' => $user->name,
        'customer_mobile' => $user->mobile,
        'customer_email' => $user->email,
    ]);
    $key = "order:{$order->id}:placed";
    $recipientSnapshot = [
        'name' => $order->customer_name,
        'mobile' => $order->customer_mobile,
        'email' => $order->customer_email,
    ];

    return compact('user', 'product', 'order', 'key', 'recipientSnapshot');
}

function createOrderPlacedNotificationIntent(CustomerNotificationService $service, Order $order): CustomerNotification
{
    return $service->forOrder(
        $order,
        CustomerNotificationType::OrderPlaced,
        [
            'order_number' => $order->order_number,
            'order_id' => $order->id,
            'amount' => $order->grand_total,
            'created_at' => $order->created_at?->toIso8601String(),
        ],
        "order:{$order->id}:placed",
    );
}

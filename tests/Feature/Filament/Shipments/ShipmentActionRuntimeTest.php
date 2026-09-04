<?php

use App\Enums\CustomerNotificationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Filament\Resources\CustomerNotifications\Pages\ViewCustomerNotification;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Shipments\Pages\ViewShipment;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function shipmentActionRuntimeUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $user;
}

function shipmentActionRuntimeOrder(): Order
{
    $product = Product::query()->create(['name' => 'Shipment action product', 'slug' => 'shipment-action-'.Str::lower(Str::random(8)), 'sku' => 'SHIP-ACT-'.Str::upper(Str::random(8)), 'type' => 'simple', 'price' => 10000]);
    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 1]], ['customer_name' => 'Runtime customer', 'customer_mobile' => '09120000000']);
}

function shipmentActionRuntimePaidOrder(): Order
{
    $order = shipmentActionRuntimeOrder();
    $orders = app(OrderService::class);
    $orders->commitInventoryForOrder($order);
    $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
    $orders->transitionStatus($order, OrderStatus::AwaitingPayment);
    $orders->transitionStatus($order, OrderStatus::Processing);

    return $order->fresh();
}

test('shipment status and tracking are changed by their real Filament actions only', function (): void {
    $admin = shipmentActionRuntimeUser(['shipments.viewAny', 'shipments.view', 'shipments.mark_ready', 'shipments.mark_shipped', 'shipments.mark_delivered', 'shipments.cancel', 'shipments.update_tracking']);
    $shipment = app(ShipmentService::class)->ensure(shipmentActionRuntimePaidOrder());

    $page = Livewire::actingAs($admin, 'web')->test(ViewShipment::class, ['record' => $shipment->getRouteKey()]);
    $page->mountAction('transition_ready')->setActionData(['note' => 'ready'])->callMountedAction();
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Ready);

    Livewire::actingAs($admin, 'web')->test(ViewShipment::class, ['record' => $shipment->getRouteKey()])
        ->mountAction('update_tracking')->setActionData(['tracking_number' => 'TRK-123', 'tracking_url' => 'https://tracking.example.test/TRK-123'])->callMountedAction()->assertHasNoActionErrors();
    expect($shipment->fresh()->tracking_number)->toBe('TRK-123')->and($shipment->fresh()->tracking_url)->toBe('https://tracking.example.test/TRK-123');

    Livewire::actingAs($admin, 'web')->test(ViewShipment::class, ['record' => $shipment->getRouteKey()])->mountAction('transition_shipped')->callMountedAction();
    Livewire::actingAs($admin, 'web')->test(ViewShipment::class, ['record' => $shipment->getRouteKey()])->mountAction('transition_delivered')->callMountedAction();
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->statusHistories()->count())->toBe(4);
});

test('order fulfillment action creates one shipment and notification retry reuses its row', function (): void {
    $admin = shipmentActionRuntimeUser(['orders.viewAny', 'orders.view', 'shipments.create', 'notifications.viewAny', 'notifications.view', 'notifications.retry']);
    $order = shipmentActionRuntimeOrder();

    Livewire::actingAs($admin, 'web')->test(ViewOrder::class, ['record' => $order->getRouteKey()])
        ->mountAction('start_fulfillment')->callMountedAction();
    expect($order->shipment()->count())->toBe(1);

    $notification = CustomerNotification::query()->create([
        'order_id' => $order->id,
        'type' => 'order_placed', 'channel' => 'development', 'status' => CustomerNotificationStatus::Failed,
        'recipient_snapshot' => ['mobile' => '09120000000'], 'payload_snapshot' => [], 'idempotency_key' => 'retry-runtime-'.Str::uuid(), 'attempts' => 1,
    ]);
    Livewire::actingAs($admin, 'web')->test(ViewCustomerNotification::class, ['record' => $notification->getRouteKey()])
        ->mountAction('retry')->callMountedAction();
    expect($notification->fresh()->id)->toBe($notification->id)
        ->and(CustomerNotification::query()->where('idempotency_key', $notification->idempotency_key)->count())->toBe(1)
        ->and($notification->fresh()->status)->toBe(CustomerNotificationStatus::Queued);
});

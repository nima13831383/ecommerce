<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Str;

function fulfillmentProduct(): Product
{
    $product = Product::query()->create([
        'name' => 'Fulfillment product',
        'slug' => 'fulfillment-product-'.Str::lower(Str::random(8)),
        'type' => 'simple',
        'sku' => 'FULFILLMENT-'.Str::upper(Str::random(8)),
        'price' => 10_000,
    ]);

    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function fulfillmentOrder(array $overrides = []): Order
{
    return app(OrderService::class)->create(
        [['product_id' => fulfillmentProduct()->id, 'quantity' => 1]],
        array_replace(['customer_name' => 'Fulfillment Customer', 'customer_mobile' => '09120000000'], $overrides),
    );
}

function paidFulfillmentOrder(): Order
{
    $order = fulfillmentOrder();
    $orders = app(OrderService::class);

    $orders->commitInventoryForOrder($order);
    $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
    $orders->transitionStatus($order, OrderStatus::AwaitingPayment);
    $orders->transitionStatus($order, OrderStatus::Processing);

    return $order->fresh('items.inventoryReservation');
}

test('shipment creation is idempotent and snapshots order shipping context', function (): void {
    $order = fulfillmentOrder();
    $order->forceFill([
        'shipping_total' => 45_000,
        'shipping_snapshot' => ['service' => 'pishtaz', 'calculation_mode' => 'calculator', 'package' => ['id' => 'box-m']],
        'shipping_address' => ['province_id' => 1, 'city_id' => 31],
    ])->save();

    $service = app(ShipmentService::class);
    $first = $service->ensure($order, null, 'شروع آماده‌سازی');
    $second = $service->ensure($order);

    expect($second->id)->toBe($first->id)
        ->and(Shipment::query()->where('order_id', $order->id)->count())->toBe(1)
        ->and($first->shipping_snapshot)->toMatchArray(['service' => 'pishtaz', 'package' => ['id' => 'box-m']])
        ->and($first->shipping_cost)->toBe('45000')
        ->and($first->status)->toBe(ShipmentStatus::Pending)
        ->and($first->statusHistories)->toHaveCount(1);
});

test('shipment transitions follow the minimal lifecycle and record history', function (): void {
    $service = app(ShipmentService::class);
    $shipment = $service->ensure(fulfillmentOrder());

    $service->transition($shipment, ShipmentStatus::Ready, null, 'بسته‌بندی شد');
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Ready);

    $paidOrder = paidFulfillmentOrder();
    $paidShipment = $service->ensure($paidOrder);
    $service->transition($paidShipment, ShipmentStatus::Ready);
    $service->transition($paidShipment, ShipmentStatus::Shipped);
    $shipped = $paidShipment->fresh();
    $shippedAt = $shipped->shipped_at;
    $service->transition($shipped, ShipmentStatus::Shipped);

    expect($shipped->fresh()->shipped_at->equalTo($shippedAt))->toBeTrue()
        ->and($shipped->fresh()->statusHistories)->toHaveCount(3);

    $service->transition($shipped, ShipmentStatus::Delivered);
    expect($shipped->fresh()->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipped->fresh()->order->status)->toBe(OrderStatus::Delivered)
        ->and($shipped->fresh()->statusHistories)->toHaveCount(4);
});

test('invalid and terminal shipment transitions are rejected', function (): void {
    $service = app(ShipmentService::class);
    $shipment = $service->ensure(fulfillmentOrder());

    expect(fn () => $service->transition($shipment, ShipmentStatus::Shipped))->toThrow(DomainException::class);

    $service->transition($shipment, ShipmentStatus::Cancelled);
    expect(fn () => $service->transition($shipment, ShipmentStatus::Ready))->toThrow(DomainException::class);
});

test('unpaid or uncommitted orders cannot be shipped', function (): void {
    $service = app(ShipmentService::class);
    $shipment = $service->ensure(fulfillmentOrder());
    $service->transition($shipment, ShipmentStatus::Ready);

    expect(fn () => $service->transition($shipment, ShipmentStatus::Shipped))->toThrow(DomainException::class);
});

test('shipment transitions never mutate inventory and cancellation does not restock', function (): void {
    $order = paidFulfillmentOrder();
    $reservation = $order->items->sole()->inventoryReservation;
    $stockBefore = $order->items->sole()->product->fresh()->stock_quantity;
    $transactionsBefore = InventoryTransaction::query()->count();
    $service = app(ShipmentService::class);
    $shipment = $service->ensure($order);

    $service->transition($shipment, ShipmentStatus::Ready);
    $service->transition($shipment, ShipmentStatus::Shipped);
    $service->transition($shipment, ShipmentStatus::Delivered);

    expect($reservation->fresh()->status)->toBe(InventoryReservationStatus::Committed)
        ->and($order->items->sole()->product->fresh()->stock_quantity)->toBe($stockBefore)
        ->and(InventoryTransaction::query()->count())->toBe($transactionsBefore);

    $cancelOrder = fulfillmentOrder();
    $cancelShipment = $service->ensure($cancelOrder);
    $service->transition($cancelShipment, ShipmentStatus::Cancelled);

    expect($cancelOrder->items->sole()->inventoryReservation->fresh()->status)->toBe(InventoryReservationStatus::Active);
});

test('tracking updates preserve string values and reject invalid URLs', function (): void {
    $service = app(ShipmentService::class);
    $shipment = $service->ensure(fulfillmentOrder());
    $updated = $service->updateTracking($shipment, '0012-AZ-۹', 'https://post.example/0012-AZ-9', 'رهگیری ثبت شد');

    expect($updated->tracking_number)->toBe('0012-AZ-۹')
        ->and($updated->tracking_url)->toBe('https://post.example/0012-AZ-9')
        ->and($updated->notes)->toBe('رهگیری ثبت شد')
        ->and(fn () => $service->updateTracking($shipment, 'A-1', 'not-a-url'))->toThrow(DomainException::class);
});

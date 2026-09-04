<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\ShipmentStatusHistory;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('ensures one shipment for one order under two real MySQL workers', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = shipmentEnsureFixture();

    expect(Shipment::query()->where('order_id', $fixture['order']->id)->count())->toBe(0)
        ->and(ShipmentStatusHistory::query()->count())->toBe(0)
        ->and($fixture['order']->status)->toBe(OrderStatus::Pending)
        ->and($fixture['order']->payment_status)->toBe(OrderPaymentStatus::Unpaid)
        ->and($fixture['reservation']->status)->toBe(InventoryReservationStatus::Active)
        ->and($fixture['product']->stock_quantity)->toBe(5)
        ->and(InventoryTransaction::query()->count())->toBe(0);

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('shipment_ensure', [
        'order_id' => $fixture['order']->id,
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('')
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    $results = collect($run['results'])->pluck('json');

    expect($results->every(fn (array $result): bool => $result['ok'] === true))->toBeTrue()
        ->and($results->pluck('result.shipment_id')->unique()->values())->toHaveCount(1)
        ->and($results->pluck('result.shipment_status')->unique()->values()->all())->toBe([ShipmentStatus::Pending->value]);

    $shipment = Shipment::query()->where('order_id', $fixture['order']->id)->sole();
    $order = $fixture['order']->fresh();

    expect(Shipment::query()->where('order_id', $fixture['order']->id)->count())->toBe(1)
        ->and($results->pluck('result.shipment_id')->unique()->values()->all())->toBe([$shipment->id])
        ->and($shipment->status)->toBe(ShipmentStatus::Pending)
        ->and($shipment->statusHistories()->count())->toBe(1)
        ->and($shipment->statusHistories()->sole()->from_status)->toBeNull()
        ->and($shipment->statusHistories()->sole()->to_status)->toBe(ShipmentStatus::Pending->value)
        ->and($order->status)->toBe($fixture['orderStatus'])
        ->and($order->payment_status)->toBe($fixture['paymentStatus'])
        ->and($order->grand_total)->toBe($fixture['grandTotal'])
        ->and($order->shipping_total)->toBe($fixture['shippingTotal'])
        ->and($fixture['reservation']->fresh()->status)->toBe(InventoryReservationStatus::Active)
        ->and($fixture['product']->fresh()->stock_quantity)->toBe($fixture['stockQuantity'])
        ->and(InventoryTransaction::query()->count())->toBe($fixture['inventoryTransactionCount']);
});

it('reuses the same shipment without duplicate initial history sequentially', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = shipmentEnsureFixture();
    $service = app(ShipmentService::class);

    $first = $service->ensure($fixture['order']);
    $second = $service->ensure($fixture['order']);

    expect($second->id)->toBe($first->id)
        ->and(Shipment::query()->where('order_id', $fixture['order']->id)->count())->toBe(1)
        ->and($first->status)->toBe(ShipmentStatus::Pending)
        ->and($first->statusHistories()->count())->toBe(1);
});

/** @return array{product: Product, order: Order, reservation: InventoryReservation, orderStatus: OrderStatus, paymentStatus: OrderPaymentStatus, grandTotal: int, shippingTotal: int, stockQuantity: int, inventoryTransactionCount: int} */
function shipmentEnsureFixture(): array
{
    $product = Product::query()->create([
        'name' => 'Shipment ensure race product',
        'slug' => 'shipment-ensure-race-'.Str::lower(Str::random(12)),
        'sku' => 'SHIPMENT-ENSURE-RACE-'.Str::upper(Str::random(12)),
        'type' => 'simple',
        'price' => 100_000,
        'status' => 'published',
    ]);
    $product->forceFill(['stock_quantity' => 5, 'stock_status' => 'in_stock'])->save();

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 1],
    ], [
        'customer_name' => 'Shipment Race Customer',
        'customer_mobile' => '09120000000',
        'shipping_address' => ['province_id' => 1, 'city_id' => 31],
    ])->fresh('items.inventoryReservation');
    $reservation = $order->items->sole()->inventoryReservation;

    return [
        'product' => $product,
        'order' => $order,
        'reservation' => $reservation,
        'orderStatus' => $order->status,
        'paymentStatus' => $order->payment_status,
        'grandTotal' => $order->grand_total,
        'shippingTotal' => $order->shipping_total,
        'stockQuantity' => $product->stock_quantity,
        'inventoryTransactionCount' => InventoryTransaction::query()->count(),
    ];
}

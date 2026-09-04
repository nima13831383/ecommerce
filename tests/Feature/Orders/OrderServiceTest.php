<?php

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\TaxType;
use App\Exceptions\InsufficientStockException;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\TaxClass;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;

function orderDetails(array $overrides = []): array
{
    return array_replace([
        'customer_name' => 'Order Customer',
        'customer_mobile' => '09120000000',
    ], $overrides);
}

function orderProduct(array $overrides = []): Product
{
    $product = Product::query()->create(array_replace([
        'name' => 'Order product',
        'slug' => 'order-product-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'ORDER-'.fake()->unique()->numerify('####'),
        'price' => 12_345,
    ], $overrides));

    $product->forceFill(['stock_quantity' => 20, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function orderTax(TaxType $type, string $value): TaxClass
{
    return TaxClass::query()->create([
        'name' => "Order {$type->value} tax",
        'slug' => 'order-tax-'.fake()->unique()->numerify('###'),
        'type' => $type,
        'value' => $value,
        'is_active' => true,
    ]);
}

test('it snapshots a simple product, its integer price, and percentage tax', function (): void {
    $tax = orderTax(TaxType::Percent, '9.000');
    $product = orderProduct(['tax_class_id' => $tax->id]);

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 2],
    ], orderDetails());

    $item = $order->items->sole();

    expect($order->order_number)->toStartWith('ORD-')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($item->product_id)->toBe($product->id)
        ->and($item->product_name)->toBe('Order product')
        ->and($item->sku)->toBe($product->sku)
        ->and($item->unit_price)->toBe(12_345)
        ->and($item->quantity)->toBe(2)
        ->and($item->line_subtotal)->toBe(24_690)
        ->and($item->tax_amount)->toBe(2_222)
        ->and($item->line_total)->toBe(26_912)
        ->and($item->tax_snapshot)->toMatchArray([
            'tax_class_id' => $tax->id,
            'tax_type' => 'percent',
            'tax_value' => '9.000',
            'taxable_amount' => 24_690,
            'tax_amount' => 2_222,
        ])
        ->and($order->items_subtotal)->toBe(24_690)
        ->and($order->tax_total)->toBe(2_222)
        ->and($order->grand_total)->toBe(26_912)
        ->and($order->tax_breakdown)->toHaveCount(1);

    $product->update(['name' => 'Renamed product', 'sku' => 'NEW-SKU', 'price' => 1]);
    $tax->update(['value' => '10.000']);
    $item = $item->fresh();

    expect($item->product_name)->toBe('Order product')
        ->and($item->sku)->not->toBe('NEW-SKU')
        ->and($item->unit_price)->toBe(12_345)
        ->and($item->tax_amount)->toBe(2_222);
});

test('it snapshots a variable product identity, attributes, and effective sale price', function (): void {
    $product = orderProduct(['type' => 'variable', 'price' => 0]);
    $color = Attribute::query()->create(['name' => 'Color', 'slug' => 'color-'.fake()->unique()->numerify('###')]);
    $red = AttributeValue::query()->create(['attribute_id' => $color->id, 'value' => 'Red', 'slug' => 'red-'.fake()->unique()->numerify('###')]);
    $product->attributes()->attach($color->id, ['sort_order' => 0]);
    $product->attributeValues()->attach($red->id);
    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'combination_signature' => "{$color->id}:{$red->id}",
        'sku' => 'RED-LINE',
        'price' => 20_000,
        'sale_price' => 18_000,
        'is_active' => true,
    ]);
    $variation->forceFill(['stock_quantity' => 20, 'stock_status' => 'in_stock'])->save();
    $variation->attributeValues()->attach($red->id);

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'product_variation_id' => $variation->id, 'quantity' => 3],
    ], orderDetails());

    $item = $order->items->sole();

    expect($item->product_variation_id)->toBe($variation->id)
        ->and($item->sku)->toBe('RED-LINE')
        ->and($item->variation_attributes)->toBe(['Color' => 'Red'])
        ->and($item->unit_price)->toBe(18_000)
        ->and($item->line_subtotal)->toBe(54_000);

    $variation->update(['sku' => 'CHANGED', 'price' => 1, 'sale_price' => null]);
    $red->update(['value' => 'Blue']);

    expect($item->fresh()->sku)->toBe('RED-LINE')
        ->and($item->fresh()->variation_attributes)->toBe(['Color' => 'Red'])
        ->and($item->fresh()->unit_price)->toBe(18_000);
});

test('it snapshots fixed per-unit tax and calculates integer-safe totals', function (): void {
    $tax = orderTax(TaxType::Fixed, '1250.000');
    $product = orderProduct(['price' => 50_000, 'tax_class_id' => $tax->id]);

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 3],
    ], orderDetails());

    $item = $order->items->sole();

    expect($item->tax_amount)->toBe(3_750)
        ->and($item->tax_snapshot)->toMatchArray(['tax_type' => 'fixed', 'tax_value' => '1250.000'])
        ->and($order->items_subtotal)->toBe(150_000)
        ->and($order->discount_total)->toBe(0)
        ->and($order->shipping_total)->toBe(0)
        ->and($order->tax_total)->toBe(3_750)
        ->and($order->grand_total)->toBe(153_750);
});

test('it rejects non-positive quantities and rolls back a failed multi-item order', function (): void {
    $product = orderProduct();
    $service = app(OrderService::class);

    expect(fn () => $service->create([
        ['product_id' => $product->id, 'quantity' => 0],
    ], orderDetails()))->toThrow(DomainException::class)
        ->and(fn () => $service->create([
            ['product_id' => $product->id, 'quantity' => 1],
            ['product_id' => 999_999, 'quantity' => 1],
        ], orderDetails()))->toThrow(ModelNotFoundException::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0)
        ->and(OrderStatusHistory::count())->toBe(0);
});

test('it creates correct history and permits only explicit allowed status transitions', function (): void {
    $actor = User::factory()->create();
    $order = app(OrderService::class)->create([
        ['product_id' => orderProduct()->id, 'quantity' => 1],
    ], orderDetails(), $actor->id);

    $initial = $order->statusHistories->sole();

    expect($initial->from_status)->toBeNull()
        ->and($initial->to_status)->toBe('pending')
        ->and($initial->comment)->toBe('Initial order state.')
        ->and($initial->user_id)->toBe($actor->id);

    $order = app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment, $actor->id, 'Payment requested.');

    expect($order->status)->toBe(OrderStatus::AwaitingPayment)
        ->and($order->statusHistories)->toHaveCount(2)
        ->and($order->statusHistories->last()->from_status)->toBe('pending')
        ->and($order->statusHistories->last()->to_status)->toBe('awaiting_payment')
        ->and($order->statusHistories->last()->comment)->toBe('Payment requested.')
        ->and($order->statusHistories->last()->user_id)->toBe($actor->id)
        ->and(fn () => app(OrderService::class)->transitionStatus($order, OrderStatus::Delivered))->toThrow(DomainException::class);

    expect($order->fresh()->status)->toBe(OrderStatus::AwaitingPayment)
        ->and(OrderStatusHistory::whereBelongsTo($order)->count())->toBe(2);
});

test('it rejects direct order status mutation and retains snapshots after product deletion', function (): void {
    $product = orderProduct();
    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 1],
    ], orderDetails());

    $order->status = OrderStatus::Cancelled;

    expect(fn () => $order->save())->toThrow(LogicException::class);

    app(OrderService::class)->transitionStatus($order, OrderStatus::Cancelled);
    $product->forceDelete();
    $item = $order->items()->firstOrFail();

    expect($item->product_id)->toBeNull()
        ->and($item->product_name)->toBe('Order product')
        ->and($item->sku)->not->toBeNull();
});

test('it creates an active inventory reservation for a simple product without deducting physical stock', function (): void {
    $product = orderProduct();
    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 3],
    ], orderDetails());
    $item = $order->items->sole();
    $reservation = $item->inventoryReservation;

    expect($reservation)->not->toBeNull()
        ->and($reservation->inventory_owner_type)->toBe(Product::class)
        ->and($reservation->inventory_owner_id)->toBe($product->id)
        ->and($reservation->reference_type)->toBe('order_item')
        ->and($reservation->reference_id)->toBe((string) $item->id)
        ->and($reservation->quantity)->toBe(3)
        ->and($reservation->status)->toBe(InventoryReservationStatus::Active)
        ->and($product->fresh()->stock_quantity)->toBe(20)
        ->and(app(InventoryService::class)->availableQuantity($product->fresh()))->toBe(17);
});

test('it reserves variation inventory and never reserves the variable parent', function (): void {
    $product = orderProduct(['type' => 'variable']);
    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'combination_signature' => 'reservation:1',
        'price' => 10_000,
        'is_active' => true,
    ]);
    $variation->forceFill(['stock_quantity' => 4, 'stock_status' => 'in_stock'])->save();

    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'product_variation_id' => $variation->id, 'quantity' => 2],
    ], orderDetails());
    $reservation = $order->items->sole()->inventoryReservation;

    expect($reservation->inventory_owner_type)->toBe(ProductVariation::class)
        ->and($reservation->inventory_owner_id)->toBe($variation->id)
        ->and(InventoryReservation::where('inventory_owner_type', Product::class)->where('inventory_owner_id', $product->id)->count())->toBe(0);
});

test('it rolls back the entire order when any inventory reservation cannot be created', function (): void {
    $available = orderProduct();
    $unavailable = orderProduct();
    $unavailable->forceFill(['stock_quantity' => 1, 'stock_status' => 'in_stock'])->save();

    expect(fn () => app(OrderService::class)->create([
        ['product_id' => $available->id, 'quantity' => 2],
        ['product_id' => $unavailable->id, 'quantity' => 2],
    ], orderDetails()))->toThrow(InsufficientStockException::class);

    expect(Order::count())->toBe(0)
        ->and(OrderItem::count())->toBe(0)
        ->and(OrderStatusHistory::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(0)
        ->and($available->fresh()->stock_quantity)->toBe(20)
        ->and($unavailable->fresh()->stock_quantity)->toBe(1);
});

test('it rejects unsupported product types before creating an order or reservation', function (): void {
    $product = orderProduct(['type' => 'grouped']);

    expect(fn () => app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 1],
    ], orderDetails()))->toThrow(DomainException::class);

    expect(Order::count())->toBe(0)
        ->and(InventoryReservation::count())->toBe(0);
});

test('it reuses the order and reservations for an idempotent retry and rejects conflicting payloads', function (): void {
    $product = orderProduct();
    $details = orderDetails(['idempotency_key' => 'order-retry-1']);
    $service = app(OrderService::class);
    $first = $service->create([['product_id' => $product->id, 'quantity' => 2]], $details);
    $second = $service->create([['product_id' => $product->id, 'quantity' => 2]], $details);

    expect($second->id)->toBe($first->id)
        ->and($second->order_number)->toBe($first->order_number)
        ->and(Order::count())->toBe(1)
        ->and(OrderItem::count())->toBe(1)
        ->and(InventoryReservation::count())->toBe(1)
        ->and(fn () => $service->create([['product_id' => $product->id, 'quantity' => 3]], $details))->toThrow(DomainException::class);
});

test('competing final-stock order requests leave only one order and reservation', function (): void {
    $product = orderProduct();
    $product->forceFill(['stock_quantity' => 1, 'stock_status' => 'in_stock'])->save();
    $service = app(OrderService::class);

    $service->create([['product_id' => $product->id, 'quantity' => 1]], orderDetails());

    expect(fn () => $service->create([['product_id' => $product->id, 'quantity' => 1]], orderDetails(['customer_mobile' => '09121111111'])))->toThrow(InsufficientStockException::class);

    expect(Order::count())->toBe(1)
        ->and(InventoryReservation::count())->toBe(1)
        ->and(app(InventoryService::class)->availableQuantity($product->fresh()))->toBe(0);
});

test('it releases and commits linked order reservations idempotently and atomically', function (): void {
    $first = orderProduct();
    $second = orderProduct();
    $service = app(OrderService::class);
    $order = $service->create([
        ['product_id' => $first->id, 'quantity' => 2],
        ['product_id' => $second->id, 'quantity' => 3],
    ], orderDetails());

    $service->releaseInventoryForOrder($order);
    $service->releaseInventoryForOrder($order);

    expect($first->fresh()->stock_quantity)->toBe(20)
        ->and($second->fresh()->stock_quantity)->toBe(20)
        ->and($order->items()->get()->every(fn (OrderItem $item) => $item->inventoryReservation->status === InventoryReservationStatus::Released))->toBeTrue();

    $order = $service->create([
        ['product_id' => $first->id, 'quantity' => 2],
        ['product_id' => $second->id, 'quantity' => 3],
    ], orderDetails());
    $service->commitInventoryForOrder($order);
    $service->commitInventoryForOrder($order);

    expect($first->fresh()->stock_quantity)->toBe(18)
        ->and($second->fresh()->stock_quantity)->toBe(17)
        ->and($order->items()->get()->every(fn (OrderItem $item) => $item->inventoryReservation->status === InventoryReservationStatus::Committed))->toBeTrue()
        ->and(InventoryTransaction::where('operation', 'reservation_commit')->count())->toBe(2);
});

test('it rolls back all inventory commits when a later reservation cannot commit', function (): void {
    $first = orderProduct();
    $second = orderProduct();
    $order = app(OrderService::class)->create([
        ['product_id' => $first->id, 'quantity' => 2],
        ['product_id' => $second->id, 'quantity' => 3],
    ], orderDetails());
    $second->forceFill(['stock_quantity' => 0, 'stock_status' => 'out_of_stock'])->save();

    expect(fn () => app(OrderService::class)->commitInventoryForOrder($order))->toThrow(InsufficientStockException::class);

    expect($first->fresh()->stock_quantity)->toBe(20)
        ->and($order->items()->get()->every(fn (OrderItem $item) => $item->inventoryReservation->status === InventoryReservationStatus::Active))->toBeTrue()
        ->and(InventoryTransaction::where('operation', 'reservation_commit')->count())->toBe(0);
});

test('cancellation releases active reservations but rejects cancellation after inventory is committed', function (): void {
    $product = orderProduct();
    $service = app(OrderService::class);
    $order = $service->create([['product_id' => $product->id, 'quantity' => 2]], orderDetails());

    $service->transitionStatus($order, OrderStatus::Cancelled, comment: 'Customer cancelled.');

    expect($product->fresh()->stock_quantity)->toBe(20)
        ->and($order->fresh()->status)->toBe(OrderStatus::Cancelled)
        ->and($order->items()->sole()->inventoryReservation->status)->toBe(InventoryReservationStatus::Released);

    $order = $service->create([['product_id' => $product->id, 'quantity' => 2]], orderDetails());
    $service->commitInventoryForOrder($order);

    expect(fn () => $service->transitionStatus($order, OrderStatus::Cancelled))->toThrow(DomainException::class)
        ->and($product->fresh()->stock_quantity)->toBe(18);
});

test('linked reservations can expire without silently changing the pending order state', function (): void {
    $product = orderProduct();
    $order = app(OrderService::class)->create([
        ['product_id' => $product->id, 'quantity' => 2],
    ], orderDetails(['reservation_expires_at' => now()->addMinute()]));
    $reservation = $order->items->sole()->inventoryReservation;
    $reservation->update(['expires_at' => now()->subMinute()]);

    expect(app(InventoryService::class)->expireDueReservations())->toBe(1)
        ->and($reservation->fresh()->status)->toBe(InventoryReservationStatus::Expired)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

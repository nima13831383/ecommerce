<?php

use App\Enums\InventoryOperation;
use App\Enums\InventoryReservationStatus;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidInventoryAdjustmentException;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Inventory\InventoryService;

function inventoryProduct(string $type = 'simple', int $stock = 0): Product
{
    $product = Product::create(['name' => 'Inventory product', 'slug' => 'inventory-'.fake()->unique()->numerify('###'), 'type' => $type]);
    $product->forceFill(['stock_quantity' => $stock, 'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock'])->save();

    return $product;
}

test('it adjusts simple stock and appends an audit transaction', function (): void {
    $product = inventoryProduct(stock: 10);
    $transaction = app(InventoryService::class)->adjust($product, -3, InventoryOperation::Deduction, 'test', 'simple-1');
    expect($product->fresh()->stock_quantity)->toBe(7)->and($product->fresh()->stock_status)->toBe('in_stock')->and($transaction->quantity_before)->toBe(10)->and($transaction->quantity_after)->toBe(7)->and(InventoryTransaction::count())->toBe(1);
});

test('it uses variation stock and rejects a variable parent as an inventory owner', function (): void {
    $product = inventoryProduct('variable');
    $variation = ProductVariation::create(['product_id' => $product->id, 'combination_signature' => '1:1']);
    $variation->forceFill(['stock_quantity' => 4, 'stock_status' => 'in_stock'])->save();
    app(InventoryService::class)->adjust($variation, -2, InventoryOperation::Deduction);
    expect($variation->fresh()->stock_quantity)->toBe(2)->and(fn () => app(InventoryService::class)->adjust($product, -1))->toThrow(InvalidInventoryAdjustmentException::class);
});

test('insufficient adjustments leave stock and the ledger unchanged', function (): void {
    $product = inventoryProduct(stock: 1);
    expect(fn () => app(InventoryService::class)->adjust($product, -2))->toThrow(InsufficientStockException::class);
    expect($product->fresh()->stock_quantity)->toBe(1)->and(InventoryTransaction::count())->toBe(0);
});

test('reservations reduce availability then release and commit idempotently', function (): void {
    $product = inventoryProduct(stock: 5);
    $service = app(InventoryService::class);
    $reservation = $service->reserve($product, 3, now()->addHour(), 'checkout', 'r-1');
    expect($service->availableQuantity($product->fresh()))->toBe(2);
    $service->release($reservation);
    $service->release($reservation);
    expect($service->availableQuantity($product->fresh()))->toBe(5);
    $reservation = $service->reserve($product, 3, now()->addHour(), 'checkout', 'r-2');
    $service->commit($reservation);
    $service->commit($reservation);
    expect($product->fresh()->stock_quantity)->toBe(2)->and($reservation->fresh()->status)->toBe(InventoryReservationStatus::Committed)->and(InventoryTransaction::where('operation', InventoryOperation::ReservationCommit)->count())->toBe(1);
});

test('a reservation prevents competing use of the final unit and expires safely', function (): void {
    $product = inventoryProduct(stock: 1);
    $service = app(InventoryService::class);
    $service->reserve($product, 1, now()->addMinute(), 'checkout', 'final-a');
    expect(fn () => $service->reserve($product, 1, now()->addMinute(), 'checkout', 'final-b'))->toThrow(InsufficientStockException::class);
    $expired = $service->reserve(inventoryProduct(stock: 1), 1, now()->addMinute(), 'checkout', 'expired');
    $expired->update(['expires_at' => now()->subMinute()]);
    expect($service->expireDueReservations())->toBe(1)->and($expired->fresh()->status)->toBe(InventoryReservationStatus::Expired);
});

test('the expiry command is repeatable', function (): void {
    $reservation = app(InventoryService::class)->reserve(inventoryProduct(stock: 1), 1, now()->addMinute(), 'checkout', 'command-expiry');
    $reservation->update(['expires_at' => now()->subMinute()]);

    $this->artisan('inventory:expire-reservations')->expectsOutput('1 inventory reservation(s) expired.')->assertSuccessful();
    $this->artisan('inventory:expire-reservations')->expectsOutput('0 inventory reservation(s) expired.')->assertSuccessful();
});

test('variation metadata saves do not mutate inventory state', function (): void {
    $product = inventoryProduct('variable');
    $variation = ProductVariation::create(['product_id' => $product->id, 'combination_signature' => 'metadata:1', 'price' => 10]);
    app(InventoryService::class)->setOnHand($variation, 6, InventoryOperation::OpeningStock);
    $transactions = InventoryTransaction::count();

    $variation->update(['price' => 20, 'weight' => 1]);

    expect($variation->fresh()->stock_quantity)->toBe(6)
        ->and($variation->fresh()->stock_status)->toBe('in_stock')
        ->and(InventoryTransaction::count())->toBe($transactions);
});

<?php

use App\Enums\InventoryOperation;
use App\Enums\InventoryReservationStatus;
use App\Filament\Resources\InventoryReservations\InventoryReservationResource;
use App\Filament\Resources\InventoryTransactions\Actions\AdjustInventoryAction;
use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Models\InventoryReservation;
use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\User;
use App\Policies\InventoryReservationPolicy;
use App\Policies\InventoryTransactionPolicy;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function inventoryOperationsAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web')));

    return $user;
}

function inventoryOperationsProduct(): Product
{
    $product = Product::query()->create([
        'name' => 'محصول عملیات موجودی',
        'slug' => 'inventory-operations-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'INV-'.fake()->unique()->numerify('####'),
        'price' => 100_000,
    ]);
    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function inventoryOperationsOrder(Product $product): Order
{
    $order = app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 2]],
        ['customer_name' => 'مشتری عملیات موجودی', 'customer_mobile' => '09120000000'],
    );

    $item = $order->items()->firstOrFail();
    $reservation = app(InventoryService::class)->reserve($product, 2, now()->addHour(), 'order_item', (string) $item->id);
    $item->forceFill(['inventory_reservation_id' => $reservation->id])->save();

    return $order;
}

function runInventoryAdjustment(User $admin, Product|ProductVariation $owner, int $newOnHand, string $reason = 'اصلاح شمارش انبار'): void
{
    Livewire::actingAs($admin, 'web')
        ->test(ListInventoryTransactions::class)
        ->mountAction('adjustInventory')
        ->setActionData([
            'owner_type' => $owner::class,
            'owner_id' => $owner->id,
            'new_on_hand' => $newOnHand,
            'reason' => $reason,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();
}

test('inventory resources are read-only and protected by view permissions', function (): void {
    $reservation = InventoryReservation::query()->create([
        'inventory_owner_type' => Product::class,
        'inventory_owner_id' => inventoryOperationsProduct()->id,
        'quantity' => 1,
        'status' => InventoryReservationStatus::Active,
        'reference_type' => 'test',
        'reference_id' => 'inventory-test',
        'expires_at' => now()->addHour(),
    ]);

    $this->actingAs(User::factory()->create())->get('/admin/inventory-reservations')->assertForbidden();
    $admin = inventoryOperationsAdmin(['inventory-reservations.viewAny', 'inventory-reservations.view', 'inventory-transactions.viewAny', 'inventory-transactions.view']);

    $this->actingAs($admin)->get('/admin/inventory-reservations')->assertOk();
    $this->actingAs($admin)->get('/admin/inventory-reservations/'.$reservation->id)->assertOk();

    expect(InventoryReservationResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(InventoryTransactionResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(InventoryReservationResource::getPages())->not->toHaveKey('create')
        ->and(InventoryTransactionResource::getPages())->not->toHaveKey('edit');
});

test('reservation detail keeps product and order linkage operationally visible', function (): void {
    $product = inventoryOperationsProduct();
    $order = inventoryOperationsOrder($product);
    $reservation = $order->items()->firstOrFail()->inventoryReservation;

    $admin = inventoryOperationsAdmin(['inventory-reservations.viewAny', 'inventory-reservations.view']);

    $this->actingAs($admin)->get('/admin/inventory-reservations/'.$reservation->id)->assertOk();

    expect($reservation->orderItem->order->is($order))->toBeTrue()
        ->and($reservation->inventoryOwner->is($product))->toBeTrue();
});

test('variation owners and deleted historical owners remain inspectable', function (): void {
    $product = inventoryOperationsProduct();
    $variation = ProductVariation::query()->create([
        'product_id' => $product->id,
        'sku' => 'VAR-'.fake()->unique()->numerify('####'),
        'price' => 100_000,
        'stock_quantity' => 4,
        'stock_status' => 'in_stock',
    ]);
    $variation->forceFill(['stock_quantity' => 4, 'stock_status' => 'in_stock'])->save();
    $reservation = app(InventoryService::class)->reserve($variation, 1, now()->addHour(), 'test', 'variation-test');
    $admin = inventoryOperationsAdmin(['inventory-reservations.viewAny', 'inventory-reservations.view']);

    $this->actingAs($admin)->get('/admin/inventory-reservations/'.$reservation->id)->assertOk();

    $product->delete();
    $reservation->refresh();

    expect($reservation->inventoryOwner->is($variation))->toBeTrue();
});

test('ledger detail exposes authoritative stock summary without mutation actions', function (): void {
    $product = inventoryOperationsProduct();
    $transaction = app(InventoryService::class)->adjust($product, -2, InventoryOperation::ManualAdjustment, 'inventory-test', 'ledger-test', 'ledger test');
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view']);

    $this->actingAs($admin)->get('/admin/inventory-transactions')->assertOk();
    $this->actingAs($admin)->get('/admin/inventory-transactions/'.$transaction->id)->assertOk();

    expect(app(InventoryService::class)->availableQuantity($product->fresh()))->toBe(8)
        ->and(InventoryTransactionResource::getPages())->not->toHaveKey('create');
});

test('inventory policies deny all mutation capabilities', function (): void {
    $admin = inventoryOperationsAdmin(['inventory-reservations.viewAny', 'inventory-reservations.view', 'inventory-transactions.viewAny', 'inventory-transactions.view']);
    $reservation = InventoryReservation::query()->firstOrCreate([
        'inventory_owner_type' => Product::class,
        'inventory_owner_id' => inventoryOperationsProduct()->id,
        'reference_type' => 'test',
        'reference_id' => 'policy-test',
    ], ['quantity' => 1, 'status' => InventoryReservationStatus::Active, 'expires_at' => now()->addHour()]);
    $transaction = InventoryTransaction::query()->create([
        'inventory_owner_type' => Product::class,
        'inventory_owner_id' => $reservation->inventory_owner_id,
        'operation' => 'correction',
        'quantity_delta' => 0,
        'quantity_before' => 0,
        'quantity_after' => 0,
        'reference_type' => 'test',
        'reference_id' => 'policy-test',
    ]);

    $reservationPolicy = app(InventoryReservationPolicy::class);
    $transactionPolicy = app(InventoryTransactionPolicy::class);

    expect($reservationPolicy->view($admin, $reservation))->toBeTrue()
        ->and($reservationPolicy->create($admin))->toBeFalse()
        ->and($reservationPolicy->update($admin, $reservation))->toBeFalse()
        ->and($reservationPolicy->delete($admin, $reservation))->toBeFalse()
        ->and($transactionPolicy->view($admin, $transaction))->toBeTrue()
        ->and($transactionPolicy->create($admin))->toBeFalse()
        ->and($transactionPolicy->update($admin, $transaction))->toBeFalse()
        ->and($transactionPolicy->delete($admin, $transaction))->toBeFalse();
});

test('super-admin bypasses inventory read permissions', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $this->actingAs($superAdmin)->get('/admin/inventory-reservations')->assertOk();
});

test('authorized administrator performs an audited adjustment through the Filament action', function (): void {
    $product = inventoryOperationsProduct();
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view', 'inventory.adjust']);

    Livewire::actingAs($admin, 'web')
        ->test(ListInventoryTransactions::class)
        ->mountAction('adjustInventory')
        ->setActionData([
            'owner_type' => Product::class,
            'owner_id' => $product->id,
            'new_on_hand' => 15,
            'reason' => 'اصلاح شمارش انبار',
            'note' => 'توضیح آزمایشی',
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $transaction = InventoryTransaction::query()->latest('id')->firstOrFail();

    expect($product->fresh()->stock_quantity)->toBe(15)
        ->and($transaction->operation)->toBe(InventoryOperation::ManualAdjustment)
        ->and($transaction->quantity_before)->toBe(10)
        ->and($transaction->quantity_delta)->toBe(5)
        ->and($transaction->quantity_after)->toBe(15)
        ->and($transaction->reason)->toBe('اصلاح شمارش انبار')
        ->and($transaction->created_by)->toBe($admin->id)
        ->and($transaction->metadata['source'])->toBe('filament_manual_adjustment');
});

test('an unauthorized inventory viewer cannot access the adjustment action', function (): void {
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view']);

    expect(AdjustInventoryAction::make()->isVisible())->toBeFalse();
});

test('reduction, no-op, zero, and negative quantities follow audited workflow rules', function (): void {
    $product = inventoryOperationsProduct();
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view', 'inventory.adjust']);

    runInventoryAdjustment($admin, $product, 8);
    expect($product->fresh()->stock_quantity)->toBe(8)->and(InventoryTransaction::query()->count())->toBe(1);

    runInventoryAdjustment($admin, $product, 8);
    expect(InventoryTransaction::query()->count())->toBe(1);

    runInventoryAdjustment($admin, $product, 0);
    expect($product->fresh()->stock_quantity)->toBe(0)->and($product->fresh()->stock_status)->toBe('out_of_stock')->and(InventoryTransaction::query()->count())->toBe(2);

    Livewire::actingAs($admin, 'web')
        ->test(ListInventoryTransactions::class)
        ->mountAction('adjustInventory')
        ->setActionData(['owner_type' => Product::class, 'owner_id' => $product->id, 'new_on_hand' => -1, 'reason' => 'اصلاح منفی'])
        ->callMountedAction()
        ->assertHasActionErrors(['new_on_hand']);
});

test('variation adjustments target the variation and variable parents are rejected', function (): void {
    $product = inventoryOperationsProduct();
    $variation = ProductVariation::query()->create(['product_id' => $product->id, 'sku' => 'VAR-'.fake()->unique()->numerify('####'), 'price' => 100_000, 'stock_quantity' => 4, 'stock_status' => 'in_stock']);
    $variation->forceFill(['stock_quantity' => 4, 'stock_status' => 'in_stock'])->save();
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view', 'inventory.adjust']);

    runInventoryAdjustment($admin, $variation, 9);

    expect($variation->fresh()->stock_quantity)->toBe(9)
        ->and($product->fresh()->stock_quantity)->toBe(10)
        ->and(InventoryTransaction::query()->where('inventory_owner_type', ProductVariation::class)->where('inventory_owner_id', $variation->id)->count())->toBe(1);

    $product->forceFill(['type' => 'variable'])->save();
    runInventoryAdjustment($admin, $product->fresh(), 7);

    expect($product->fresh()->stock_quantity)->toBe(10)
        ->and(InventoryTransaction::query()->where('inventory_owner_type', Product::class)->count())->toBe(0);
});

test('active reservations protect reductions but allow safe increases', function (): void {
    $product = inventoryOperationsProduct();
    app(InventoryService::class)->reserve($product, 8, now()->addHour(), 'test', 'adjustment-reservation');
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view', 'inventory.adjust']);

    runInventoryAdjustment($admin, $product, 5);

    expect($product->fresh()->stock_quantity)->toBe(10)
        ->and(InventoryReservation::query()->where('reference_id', 'adjustment-reservation')->where('status', InventoryReservationStatus::Active)->exists())->toBeTrue()
        ->and(InventoryTransaction::query()->count())->toBe(0);

    runInventoryAdjustment($admin, $product, 20);

    expect($product->fresh()->stock_quantity)->toBe(20)
        ->and(app(InventoryService::class)->availableQuantity($product->fresh()))->toBe(12)
        ->and(InventoryTransaction::query()->latest('id')->first()->quantity_delta)->toBe(10);
});

test('the audited action requires a reason', function (): void {
    $product = inventoryOperationsProduct();
    $admin = inventoryOperationsAdmin(['inventory-transactions.viewAny', 'inventory-transactions.view', 'inventory.adjust']);

    Livewire::actingAs($admin, 'web')
        ->test(ListInventoryTransactions::class)
        ->mountAction('adjustInventory')
        ->setActionData(['owner_type' => Product::class, 'owner_id' => $product->id, 'new_on_hand' => 15, 'reason' => ''])
        ->callMountedAction()
        ->assertHasActionErrors(['reason']);

    expect($product->fresh()->stock_quantity)->toBe(10)->and(InventoryTransaction::query()->count())->toBe(0);
});

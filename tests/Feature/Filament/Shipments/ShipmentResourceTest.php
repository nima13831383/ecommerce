<?php

use App\Filament\Resources\Shipments\ShipmentResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Orders\OrderService;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function shipmentAdminUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function shipmentResourceOrder(): Order
{
    $product = Product::query()->create([
        'name' => 'Shipment resource product',
        'slug' => 'shipment-resource-'.Str::lower(Str::random(8)),
        'sku' => 'SHIPMENT-RESOURCE-'.Str::upper(Str::random(8)),
        'type' => 'simple',
        'price' => 10_000,
    ]);
    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 1]],
        ['customer_name' => 'Shipment Resource Customer', 'customer_mobile' => '09120000000'],
    );
}

test('shipment resource is read protected and has no generic mutation pages', function (): void {
    $shipment = app(ShipmentService::class)->ensure(shipmentResourceOrder());

    $this->actingAs(User::factory()->create())
        ->get('/admin/shipments')
        ->assertForbidden();

    $admin = shipmentAdminUser(['shipments.viewAny', 'shipments.view']);

    $this->actingAs($admin)
        ->get('/admin/shipments')
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/shipments/{$shipment->id}")
        ->assertOk();

    expect(ShipmentResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(ShipmentResource::getPages())->not->toHaveKey('create')
        ->and(ShipmentResource::getPages())->not->toHaveKey('edit');
});

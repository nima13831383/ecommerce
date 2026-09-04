<?php

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\Orders\OrderService;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function orderAdminUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function filamentOrderProduct(array $overrides = []): Product
{
    $product = Product::query()->create(array_replace([
        'name' => 'محصول آزمایشی سفارش',
        'slug' => 'filament-order-product-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'FILAMENT-'.fake()->unique()->numerify('####'),
        'price' => 125_000,
    ], $overrides));

    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return $product;
}

function filamentOrder(array $overrides = []): Order
{
    return app(OrderService::class)->create(
        [['product_id' => filamentOrderProduct()->id, 'quantity' => 2]],
        array_replace([
            'customer_name' => 'مشتری تاریخی',
            'customer_mobile' => '09120000000',
            'customer_email' => 'snapshot@example.test',
            'billing_address' => ['city' => 'تهران', 'street' => 'خیابان آزمایش'],
            'shipping_address' => ['city' => 'تهران', 'street' => 'خیابان ارسال'],
        ], $overrides),
    );
}

test('orders require view permissions and have no generic create or edit page', function (): void {
    $order = filamentOrder();

    $this->actingAs(User::factory()->create())
        ->get('/admin/orders')
        ->assertForbidden();

    $admin = orderAdminUser(['orders.viewAny', 'orders.view']);

    $this->actingAs($admin)
        ->get('/admin/orders')
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}")
        ->assertOk();

    $resource = app(OrderResource::class);

    expect($resource::getPages())->toHaveKeys(['index', 'view'])
        ->and($resource::getPages())->not->toHaveKey('create')
        ->and($resource::getPages())->not->toHaveKey('edit');
});

test('the order detail is independent from current product records', function (): void {
    $order = filamentOrder();
    $item = $order->items()->firstOrFail();
    $product = $item->product;

    $product->update(['name' => 'نام فعلی متفاوت', 'sku' => 'CURRENT-SKU']);
    app(OrderService::class)->transitionStatus($order, OrderStatus::Cancelled);
    $product->forceDelete();

    $admin = orderAdminUser(['orders.viewAny', 'orders.view']);

    $this->actingAs($admin)
        ->get("/admin/orders/{$order->id}")
        ->assertOk();

    expect($item->fresh()->product_name)->toBe('محصول آزمایشی سفارش')
        ->and($item->fresh()->sku)->toStartWith('FILAMENT-');
});

test('status transitions require the dedicated permission and domain service path', function (): void {
    $order = filamentOrder();
    $policy = app(OrderPolicy::class);
    $admin = orderAdminUser(['orders.viewAny', 'orders.view']);

    expect($policy->updateStatus($admin, $order))->toBeFalse();

    $admin->givePermissionTo(Permission::findOrCreate('orders.update_status', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($policy->updateStatus($admin->fresh(), $order))->toBeTrue();

    $order->status = OrderStatus::Cancelled;

    expect(fn () => $order->save())->toThrow(LogicException::class);
});

test('order resource has no payment or inventory mutation surface', function (): void {
    $resource = OrderResource::class;

    expect($resource::getPages())->toHaveKeys(['index', 'view'])
        ->and($resource::getPages())->not->toHaveKey('edit')
        ->and($resource::getPages())->not->toHaveKey('create');
});

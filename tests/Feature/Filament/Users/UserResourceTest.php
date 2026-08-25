<?php

use App\Enums\PaymentStatus;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Hash;
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

function userAdmin(array $permissions, ?string $role = null): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web')));

    if ($role !== null) {
        $user->assignRole(Role::findOrCreate($role, 'web'));
    }

    return $user;
}

function userWithOrder(): array
{
    $user = User::factory()->create(['name' => 'مشتری آزمایشی', 'email' => 'customer-user@example.test']);
    $product = Product::query()->create([
        'name' => 'محصول کاربر',
        'slug' => 'user-product-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'USER-'.fake()->unique()->numerify('####'),
        'price' => 100_000,
    ]);
    $product->forceFill(['stock_quantity' => 5, 'stock_status' => 'in_stock'])->save();
    $order = app(OrderService::class)->create([['product_id' => $product->id, 'quantity' => 1]], [
        'user_id' => $user->id,
        'customer_name' => $user->name,
        'customer_mobile' => '09120000000',
        'customer_email' => $user->email,
    ]);
    $payment = Payment::query()->create([
        'payment_number' => 'USER-PAY-'.fake()->unique()->numerify('#####'),
        'order_id' => $order->id,
        'method' => 'online_gateway',
        'gateway' => 'test-gateway',
        'status' => PaymentStatus::Failed,
        'currency' => 'IRR',
        'amount' => $order->grand_total,
        'paid_amount' => 0,
        'refunded_amount' => 0,
        'reconciliation_required' => false,
    ]);

    return [$user, $order, $payment];
}

test('users are protected, searchable through a read-oriented resource, and have no create or force-delete page', function (): void {
    [$user] = userWithOrder();
    $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
    $admin = userAdmin(['users.viewAny', 'users.view', 'users.update']);

    $this->actingAs($admin)->get('/admin/users')->assertOk();
    $this->actingAs($admin)->get('/admin/users/'.$user->id)->assertOk();
    $this->actingAs($admin)->get('/admin/users/'.$user->id.'/edit')->assertOk();

    expect(UserResource::getPages())->toHaveKeys(['index', 'view', 'edit'])
        ->and(UserResource::getPages())->not->toHaveKey('create')
        ->and(UserResource::getPages())->not->toHaveKey('force-delete');
});

test('user detail exposes order and payment history without authentication secrets', function (): void {
    [$user, $order, $payment] = userWithOrder();
    $admin = userAdmin(['users.viewAny', 'users.view']);

    $response = $this->actingAs($admin)->get('/admin/users/'.$user->id);

    $response->assertOk()->assertDontSee($user->password)->assertDontSee($user->remember_token);
    expect($user->orders()->whereKey($order)->exists())->toBeTrue()
        ->and($order->payments()->whereKey($payment)->exists())->toBeTrue();
});

test('authorized user is soft-deleted and restored through Filament actions while history survives', function (): void {
    [$user, $order, $payment] = userWithOrder();
    $admin = userAdmin(['users.viewAny', 'users.view', 'users.delete', 'users.restore']);

    Livewire::actingAs($admin, 'web')
        ->test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->mountAction('delete')
        ->callMountedAction();

    expect(User::query()->whereKey($user)->exists())->toBeFalse()
        ->and(User::withTrashed()->whereKey($user)->value('deleted_at'))->not->toBeNull()
        ->and(Order::query()->whereKey($order)->exists())->toBeTrue()
        ->and(Payment::query()->whereKey($payment)->exists())->toBeTrue();

    Livewire::actingAs($admin, 'web')
        ->test(ViewUser::class, ['record' => $user->getRouteKey()])
        ->mountAction('restore')
        ->callMountedAction();

    expect(User::query()->whereKey($user)->exists())->toBeTrue();
});

test('self-delete and ordinary-admin deletion of super-admin are denied', function (): void {
    $admin = userAdmin(['users.viewAny', 'users.view', 'users.delete'], 'admin');
    $superAdmin = userAdmin(['users.viewAny', 'users.view'], 'super-admin');
    $policy = app(UserPolicy::class);

    expect($policy->delete($admin, $admin))->toBeFalse()
        ->and($policy->delete($admin, $superAdmin))->toBeFalse();
});

test('role management is separate and ordinary admins cannot grant super-admin', function (): void {
    $admin = userAdmin(['users.viewAny', 'users.view', 'users.manage_roles'], 'admin');
    $target = User::factory()->create();
    $policy = app(UserPolicy::class);

    expect($policy->manageRoles($admin, $target, ['admin']))->toBeTrue()
        ->and($policy->manageRoles($admin, $target, ['super-admin']))->toBeFalse();

    Livewire::actingAs($admin, 'web')
        ->test(ViewUser::class, ['record' => $target->getRouteKey()])
        ->mountAction('manageRoles')
        ->setActionData(['roles' => ['admin']])
        ->callMountedAction();

    expect($target->fresh()->hasRole('admin'))->toBeTrue()
        ->and($target->fresh()->hasRole('super-admin'))->toBeFalse();
});

test('super-admin protection prevents removing the last super-admin role', function (): void {
    $superAdmin = userAdmin(['users.viewAny', 'users.view', 'users.manage_roles'], 'super-admin');
    $policy = app(UserPolicy::class);

    expect($policy->manageRoles($superAdmin, $superAdmin, []))->toBeFalse();
});

test('soft-deleted admins cannot access the admin panel', function (): void {
    $admin = userAdmin(['users.viewAny'], 'admin');
    $admin->delete();

    $this->actingAs($admin)->get('/admin')->assertForbidden();
});

test('user edit form does not expose passwords or authentication tokens', function (): void {
    $user = User::factory()->create();
    $admin = userAdmin(['users.viewAny', 'users.view', 'users.update']);

    $response = $this->actingAs($admin)->get('/admin/users/'.$user->id.'/edit');

    $response->assertOk()->assertDontSee('password')->assertDontSee('remember_token')->assertDontSee(Hash::make('password'));
});

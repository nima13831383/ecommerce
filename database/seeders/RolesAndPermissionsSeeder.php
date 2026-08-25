<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /** @var list<string> */
    private const PERMISSIONS = [
        'products.view',
        'products.create',
        'products.update',
        'products.delete',
        'products.restore',
        'products.force-delete',
        'categories.view',
        'categories.create',
        'categories.update',
        'categories.delete',
        'categories.restore',
        'categories.force-delete',
        'brands.view',
        'brands.create',
        'brands.update',
        'brands.delete',
        'brands.restore',
        'brands.force-delete',
        'tags.view',
        'tags.create',
        'tags.update',
        'tags.delete',
        'coupons.view',
        'coupons.create',
        'coupons.update',
        'coupons.delete',
        'tax-classes.view',
        'tax-classes.create',
        'tax-classes.update',
        'tax-classes.delete',
        'settings.view',
        'settings.update',
        'posts.viewAny',
        'posts.view',
        'posts.create',
        'posts.update',
        'posts.delete',
        'posts.restore',
        'posts.publish',
        'post-categories.view',
        'post-categories.create',
        'post-categories.update',
        'post-categories.delete',
        'post-tags.view',
        'post-tags.create',
        'post-tags.update',
        'post-tags.delete',
        'orders.viewAny',
        'orders.view',
        'orders.update_status',
        'payments.viewAny',
        'payments.view',
        'inventory-reservations.viewAny',
        'inventory-reservations.view',
        'inventory-transactions.viewAny',
        'inventory-transactions.view',
        'inventory.adjust',
        'users.viewAny',
        'users.view',
        'users.update',
        'users.delete',
        'users.restore',
        'users.manage_roles',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = collect(self::PERMISSIONS)
            ->map(fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'));

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');

        $superAdmin->givePermissionTo($permissions);
        $admin->givePermissionTo($permissions);

        $initialSuperAdminEmail = config('admin.initial_super_admin_email');

        if (filled($initialSuperAdminEmail)) {
            User::query()
                ->where('email', $initialSuperAdminEmail)
                ->first()
                ?->assignRole($superAdmin);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

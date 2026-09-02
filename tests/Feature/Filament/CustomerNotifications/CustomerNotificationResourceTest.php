<?php

use App\Filament\Resources\CustomerNotifications\CustomerNotificationResource;
use App\Models\CustomerNotification;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function notificationAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

test('notification administration is read protected and exposes only a controlled retry action', function (): void {
    $notification = CustomerNotification::query()->create([
        'type' => 'order_placed',
        'channel' => 'development',
        'status' => 'queued',
        'recipient_snapshot' => ['mobile' => '09120000000'],
        'payload_snapshot' => ['order_number' => 'ORD-TEST'],
        'idempotency_key' => 'filament-notification-test',
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/admin/customer-notifications')
        ->assertForbidden();

    $admin = notificationAdmin(['notifications.viewAny', 'notifications.view']);

    $this->actingAs($admin)
        ->get('/admin/customer-notifications')
        ->assertOk();

    $this->actingAs($admin)
        ->get(CustomerNotificationResource::getUrl('view', ['record' => $notification]))
        ->assertOk();

    expect(CustomerNotificationResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(CustomerNotificationResource::getPages())->not->toHaveKey('create')
        ->and(CustomerNotificationResource::getPages())->not->toHaveKey('edit');
});

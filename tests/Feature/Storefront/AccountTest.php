<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guest is redirected from account pages', function (): void {
    $this->get('/account')->assertRedirect(route('login'));
    $this->get('/account/profile')->assertRedirect(route('login'));
    $this->get('/account/addresses')->assertRedirect(route('login'));
});

test('authenticated customer sees and can update their profile', function (): void {
    $user = User::factory()->create(['name' => 'Old Name']);
    $this->actingAs($user);

    $this->get('/account')->assertOk()->assertSee('Old Name')->assertDontSee('permissions');
    $this->get('/account/profile')->assertOk()->assertSee('Old Name');

    $this->patch('/account/profile', [
        'name' => 'New Name',
        'email' => $user->email,
    ])->assertRedirect(route('storefront.account.profile'));

    expect($user->fresh()->name)->toBe('New Name');
});

test('password update remains available in the storefront profile', function (): void {
    $user = User::factory()->create(['password' => Hash::make('old-password')]);
    $this->actingAs($user);

    $this->put('/password', [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('guest auth pages use the storefront presentation', function (): void {
    foreach (['/login', '/register', '/forgot-password', '/reset-password/test-token'] as $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertSee('auth-form')
            ->assertSee('<header>', false)
            ->assertDontSee('auth-trust', false)
            ->assertDontSee('site-footer', false);
    }

    $this->get('/login')->assertSee('password-toggle', false)->assertSee('#i-eye', false);
    $this->get('/reset-password/test-token')->assertSee('password-toggle', false)->assertSee('#i-eye', false);
});

test('customers can authenticate and logout through Breeze web routes', function (): void {
    $user = User::factory()->create(['password' => Hash::make('password')]);

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
    $this->get('/')->assertSee(route('storefront.account'));

    $this->post('/logout')->assertRedirect('/');
    $this->assertGuest();
});

test('registration keeps the supported user fields only', function (): void {
    $response = $this->post('/register', [
        'name' => 'Storefront Customer',
        'email' => 'storefront@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', ['email' => 'storefront@example.test', 'name' => 'Storefront Customer']);
});

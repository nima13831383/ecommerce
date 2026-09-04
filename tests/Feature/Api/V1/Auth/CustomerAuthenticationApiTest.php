<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

test('customers can register through the JSON session boundary', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Storefront Customer',
        'email' => 'customer@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified']])
        ->assertJsonPath('data.email', 'customer@example.com')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.roles');

    $this->assertAuthenticatedAs(User::query()->where('email', 'customer@example.com')->first(), 'web');
});

test('registration validation uses the shared API error contract', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonStructure(['message', 'errors']);
    $this->assertGuest('web');
});

test('customers can log in and the session identifier is regenerated', function (): void {
    $user = User::factory()->create(['email' => 'login@example.com']);

    $this->get('/login');
    $sessionBefore = $this->app['session']->getId();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertOk()->assertJsonPath('data.id', $user->id);
    $this->assertAuthenticatedAs($user, 'web');
    expect($this->app['session']->getId())->not->toBe($sessionBefore);
});

test('incorrect credentials return a stable authentication error', function (): void {
    $user = User::factory()->create(['email' => 'wrong-password@example.com']);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'invalid_credentials')
        ->assertJsonMissingPath('data.password');
    $this->assertGuest('web');
});

test('JSON login retains Breeze throttling', function (): void {
    $email = 'throttled-'.uniqid().'@example.com';

    $statuses = [];

    foreach (range(1, 7) as $attempt) {
        $statuses[] = $this->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ])->status();

        if (end($statuses) === 429) {
            break;
        }
    }

    expect($statuses)->toContain(429);
    $this->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => 'wrong-password',
    ])->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

test('authenticated customers can retrieve only their public account resource', function (): void {
    $user = User::factory()->create();
    Role::findOrCreate('admin', 'web');
    $user->assignRole('admin');

    $response = $this->actingAs($user, 'web')->getJson('/api/v1/auth/me');

    $response->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonStructure(['data' => ['id', 'name', 'email', 'email_verified']])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.roles')
        ->assertJsonMissingPath('data.permissions')
        ->assertJsonMissingPath('data.deleted_at');
});

test('unauthenticated customer endpoints return JSON 401 instead of a login redirect', function (): void {
    $response = $this->getJson('/api/v1/auth/me');

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonStructure(['message', 'errors', 'code'])
        ->assertHeaderMissing('Location');
});

test('logout invalidates the web session and removes customer authentication', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user, 'web')
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJsonPath('data', null);

    $this->assertGuest('web');
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('soft deleted customers cannot authenticate through JSON login', function (): void {
    $user = User::factory()->create(['email' => 'deleted@example.com']);
    $user->delete();

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'deleted@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'invalid_credentials');
    $this->assertGuest('web');
});

test('customer mutation routes use the web middleware boundary', function (): void {
    foreach (['api.v1.auth.register', 'api.v1.auth.login', 'api.v1.auth.logout'] as $name) {
        expect(Route::getRoutes()->getByName($name)->gatherMiddleware())->toContain('web');
    }

    expect(Route::getRoutes()->getByName('api.v1.auth.me')->gatherMiddleware())
        ->toContain('web')
        ->toContain('auth:web');
});

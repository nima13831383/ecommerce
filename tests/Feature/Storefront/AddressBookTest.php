<?php

use App\Models\User;
use App\Services\Addresses\AddressService;

function storefrontAddressPayload(array $overrides = []): array
{
    return [
        'type' => 'shipping',
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'mobile' => '09120000000',
        'province_id' => 1,
        'city_id' => 1,
        'postal_code' => '0123456789',
        'address_line' => 'خیابان اصلی، کوچه اول',
        'plaque' => '12',
        'unit' => '3',
        'is_default' => 1,
        ...$overrides,
    ];
}

test('authenticated customers can create, edit, list and delete their addresses', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/account/addresses')->assertOk()->assertSee('استان');
    $create = $this->post('/account/addresses', storefrontAddressPayload());
    $create->assertRedirect(route('storefront.account.addresses'));
    $address = $user->addresses()->firstOrFail();

    $this->get('/account/addresses')->assertSee('خیابان اصلی');
    $this->patch("/account/addresses/{$address->id}", storefrontAddressPayload(['address_line' => 'خیابان به‌روزشده']))
        ->assertRedirect(route('storefront.account.addresses'));
    expect($address->fresh()->address_line)->toBe('خیابان به‌روزشده');

    $this->delete("/account/addresses/{$address->id}")->assertRedirect(route('storefront.account.addresses'));
    expect($address->fresh()->trashed())->toBeTrue();
});

test('address geography endpoint uses the shipping dataset', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->getJson('/locations/provinces/1/cities');
    $response->assertOk()->assertJsonFragment(['id' => 1, 'name' => 'تهران']);
    $this->getJson('/locations/provinces/999999/cities')->assertNotFound();
});

test('address validation rejects invalid geography and cross-user tampering', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $this->actingAs($user);

    $this->from('/account/addresses')
        ->post('/account/addresses', storefrontAddressPayload(['city_id' => 4391]))
        ->assertRedirect('/account/addresses')
        ->assertSessionHasErrors('address');

    $address = app(AddressService::class)->create($other, storefrontAddressPayload(['is_default' => 0, 'address_line' => 'Other private address']));
    $this->flushSession();
    $this->actingAs($user);
    $this->get("/account/addresses?edit={$address->id}")->assertOk()->assertDontSee('Other private address');
    $this->patch("/account/addresses/{$address->id}", storefrontAddressPayload(['address_line' => 'hijacked']))
        ->assertRedirect(route('storefront.account.addresses'))
        ->assertSessionHasErrors('address');
    $this->delete("/account/addresses/{$address->id}")
        ->assertRedirect(route('storefront.account.addresses'))
        ->assertSessionHasErrors('address');
    expect($address->fresh()->trashed())->toBeFalse();
});

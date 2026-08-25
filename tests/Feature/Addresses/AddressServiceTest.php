<?php

use App\Enums\AddressType;
use App\Exceptions\AddressValidationException;
use App\Models\Order;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;

function addressData(array $overrides = []): array
{
    return [
        'type' => AddressType::Shipping->value,
        'first_name' => 'علی',
        'last_name' => 'رضایی',
        'mobile' => '۰۹۱۲۰۰۰۰۰۰۰',
        'province_id' => 1,
        'city_id' => 1,
        'postal_code' => '0123456789',
        'address_line' => 'خیابان اصلی، کوچه اول',
        'plaque' => '12',
        'unit' => '3',
        'latitude' => 35.7,
        'longitude' => 51.4,
        'is_default' => false,
        ...$overrides,
    ];
}

test('address fields match the schema and resolve shipping geographic names', function (): void {
    $user = User::factory()->create();
    $service = app(AddressService::class);
    $address = $service->create($user, addressData());

    expect($address->type)->toBe(AddressType::Shipping)
        ->and($address->mobile)->toBe('09120000000')
        ->and($address->postal_code)->toBe('0123456789')
        ->and($address->latitude)->toBe('35.7000000')
        ->and($address->longitude)->toBe('51.4000000')
        ->and($service->resolveLocation($address->province_id, $address->city_id))->toMatchArray([
            'province_name' => 'تهران',
            'city_name' => 'تهران',
        ]);
});

test('province and city must be valid and belong together in the shipping dataset', function (): void {
    $user = User::factory()->create();
    $service = app(AddressService::class);

    expect(fn () => $service->create($user, addressData(['province_id' => 999999, 'city_id' => 31])))
        ->toThrow(AddressValidationException::class)
        ->and(fn () => $service->create($user, addressData(['province_id' => 1, 'city_id' => 4391])))
        ->toThrow(AddressValidationException::class)
        ->and(fn () => $service->create($user, addressData(['province_id' => 1, 'city_id' => null])))
        ->toThrow(AddressValidationException::class);
});

test('default address behavior is transactionally limited to one user address', function (): void {
    $user = User::factory()->create();
    $service = app(AddressService::class);
    $first = $service->create($user, addressData(['is_default' => true]));
    $second = $service->create($user, addressData(['city_id' => 1, 'is_default' => true]));

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue()
        ->and($user->fresh()->defaultAddress()->count())->toBe(1);
});

test('updates revalidate ownership, location, postal code, and coordinates atomically', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $service = app(AddressService::class);
    $address = $service->create($user, addressData());

    expect(fn () => $service->update($user, $address, ['province_id' => 1, 'city_id' => 4391]))
        ->toThrow(AddressValidationException::class)
        ->and(fn () => $service->update($otherUser, $address, ['first_name' => 'جعلی']))
        ->toThrow(AddressValidationException::class)
        ->and($address->fresh()->first_name)->toBe('علی');
});

test('postal codes preserve leading zeros and coordinates enforce geographic bounds', function (): void {
    $user = User::factory()->create();
    $service = app(AddressService::class);

    expect(fn () => $service->create($user, addressData(['postal_code' => '123'])))
        ->toThrow(AddressValidationException::class)
        ->and(fn () => $service->create($user, addressData(['latitude' => 91])))
        ->toThrow(AddressValidationException::class);
});

test('soft deleting an address does not affect order history', function (): void {
    $user = User::factory()->create();
    $service = app(AddressService::class);
    $address = $service->create($user, addressData());
    $order = Order::query()->create([
        'order_number' => 'ADDR-'.fake()->unique()->numerify('######'),
        'user_id' => $user->id,
        'customer_name' => 'علی رضایی',
        'customer_mobile' => '09120000000',
        'currency' => 'IRR',
        'shipping_address' => ['address_line' => $address->address_line],
    ]);

    $service->delete($user, $address);

    expect($address->fresh()->trashed())->toBeTrue()
        ->and($order->fresh()->shipping_address['address_line'])->toBe('خیابان اصلی، کوچه اول');
});

test('address uses the same location loader instance and data as shipping', function (): void {
    $loader = app(WordpressShippingDataLoader::class);
    $service = app(AddressService::class);

    expect($service->resolveLocation(2, 4391))->toMatchArray([
        'province_name' => $loader->provinceName(2),
        'city_name' => $loader->cityName(4391, 2),
    ]);
});

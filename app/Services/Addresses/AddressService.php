<?php

namespace App\Services\Addresses;

use App\Enums\AddressType;
use App\Exceptions\AddressValidationException;
use App\Models\Address;
use App\Models\User;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use Illuminate\Support\Facades\DB;

class AddressService
{
    public function __construct(
        private readonly WordpressShippingDataLoader $geography,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(User $user, array $data): Address
    {
        return DB::transaction(function () use ($user, $data): Address {
            $user = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $attributes = $this->validatedAttributes($data);

            if ($attributes['is_default']) {
                $this->clearDefault($user);
            }

            return $user->addresses()->create($attributes);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(User $user, Address $address, array $data): Address
    {
        return DB::transaction(function () use ($user, $address, $data): Address {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $address = Address::query()
                ->whereBelongsTo($user)
                ->whereKey($address->id)
                ->lockForUpdate()
                ->first();

            if (! $address) {
                throw new AddressValidationException('این آدرس متعلق به کاربر انتخاب‌شده نیست.');
            }

            $attributes = $this->validatedAttributes(array_replace($address->only([
                'type',
                'first_name',
                'last_name',
                'mobile',
                'province_id',
                'city_id',
                'postal_code',
                'address_line',
                'plaque',
                'unit',
                'latitude',
                'longitude',
                'is_default',
                'company',
            ]), $data));

            if ($attributes['is_default']) {
                $this->clearDefault($user, $address->id);
            }

            $address->forceFill($attributes)->save();

            return $address->fresh();
        });
    }

    public function delete(User $user, Address $address): void
    {
        DB::transaction(function () use ($user, $address): void {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $address = Address::query()
                ->whereBelongsTo($user)
                ->whereKey($address->id)
                ->lockForUpdate()
                ->first();

            if (! $address) {
                throw new AddressValidationException('این آدرس متعلق به کاربر انتخاب‌شده نیست.');
            }

            $address->delete();
        });
    }

    public function getForUser(User $user, int $addressId): Address
    {
        $address = Address::query()
            ->whereBelongsTo($user)
            ->whereKey($addressId)
            ->first();

        if (! $address) {
            throw new AddressValidationException('آدرس انتخاب‌شده متعلق به این کاربر نیست.');
        }

        return $address;
    }

    /** @return array<string, mixed> */
    public function snapshot(Address $address): array
    {
        $location = null;

        if ($address->province_id !== null || $address->city_id !== null) {
            if ($address->province_id === null || $address->city_id === null) {
                throw new AddressValidationException('استان و شهر آدرس کامل نیستند.');
            }

            $location = $this->resolveLocation((int) $address->province_id, (int) $address->city_id);
        }

        return [
            'type' => $address->type?->value ?? $address->type,
            'first_name' => $address->first_name,
            'last_name' => $address->last_name,
            'mobile' => $address->mobile,
            'company' => $address->company,
            'province_id' => $location['province_id'] ?? null,
            'province_name' => $location['province_name'] ?? null,
            'city_id' => $location['city_id'] ?? null,
            'city_name' => $location['city_name'] ?? null,
            'postal_code' => $address->postal_code,
            'address_line' => $address->address_line,
            'plaque' => $address->plaque,
            'unit' => $address->unit,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }

    /** @return array{province_id: int, province_name: string, city_id: int, city_name: string} */
    public function resolveLocation(int $provinceId, int $cityId): array
    {
        $provinceName = $this->geography->provinceName($provinceId);

        if ($provinceName === null) {
            throw new AddressValidationException('استان انتخاب‌شده معتبر نیست.');
        }

        $cityName = $this->geography->cityName($cityId, $provinceId);

        if ($cityName === null) {
            throw new AddressValidationException('شهر انتخاب‌شده با استان انتخاب‌شده همخوانی ندارد.');
        }

        return [
            'province_id' => $provinceId,
            'province_name' => $provinceName,
            'city_id' => $cityId,
            'city_name' => $cityName,
        ];
    }

    /** @return array<string, mixed> */
    private function validatedAttributes(array $data): array
    {
        $type = $this->addressType($data['type'] ?? AddressType::Both->value);
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName = trim((string) ($data['last_name'] ?? ''));
        $mobile = $this->normalizeDigits(trim((string) ($data['mobile'] ?? '')));
        $addressLine = trim((string) ($data['address_line'] ?? ''));

        if ($firstName === '' || $lastName === '' || $addressLine === '') {
            throw new AddressValidationException('نام، نام خانوادگی و آدرس الزامی هستند.');
        }

        if (! preg_match('/^09\d{9}$/', $mobile)) {
            throw new AddressValidationException('شماره موبایل معتبر نیست.');
        }

        $provinceId = $this->nullablePositiveInteger($data['province_id'] ?? null, 'شناسه استان');
        $cityId = $this->nullablePositiveInteger($data['city_id'] ?? null, 'شناسه شهر');

        if (($provinceId === null) !== ($cityId === null)) {
            throw new AddressValidationException('استان و شهر باید با هم ارسال شوند.');
        }

        if ($provinceId !== null && $cityId !== null) {
            $this->resolveLocation($provinceId, $cityId);
        }

        $postalCode = $this->normalizeDigitsNullable($data['postal_code'] ?? null);
        if ($postalCode !== null && ! preg_match('/^\d{10}$/', $postalCode)) {
            throw new AddressValidationException('کد پستی باید ده رقم باشد.');
        }

        $latitude = $this->coordinate($data['latitude'] ?? null, -90, 90, 'عرض جغرافیایی');
        $longitude = $this->coordinate($data['longitude'] ?? null, -180, 180, 'طول جغرافیایی');

        return [
            'type' => $type->value,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'mobile' => $mobile,
            'province_id' => $provinceId,
            'city_id' => $cityId,
            'postal_code' => $postalCode,
            'address_line' => $addressLine,
            'plaque' => $this->nullableString($data['plaque'] ?? null),
            'unit' => $this->nullableString($data['unit'] ?? null),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_default' => (bool) ($data['is_default'] ?? false),
            'company' => $this->nullableString($data['company'] ?? null),
        ];
    }

    private function addressType(mixed $type): AddressType
    {
        $resolved = $type instanceof AddressType ? $type : AddressType::tryFrom((string) $type);

        if (! $resolved) {
            throw new AddressValidationException('نوع آدرس معتبر نیست.');
        }

        return $resolved;
    }

    private function clearDefault(User $user, ?int $exceptId = null): void
    {
        $user->addresses()
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }

    private function nullablePositiveInteger(mixed $value, string $label): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw new AddressValidationException("{$label} معتبر نیست.");
        }

        return (int) $value;
    }

    private function coordinate(mixed $value, float $minimum, float $maximum, string $label): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (float) $value < $minimum || (float) $value > $maximum) {
            throw new AddressValidationException("{$label} معتبر نیست.");
        }

        return number_format((float) $value, 7, '.', '');
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function normalizeDigitsNullable(mixed $value): ?string
    {
        $value = $this->normalizeDigits(trim((string) ($value ?? '')));

        return $value === '' ? null : $value;
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }
}

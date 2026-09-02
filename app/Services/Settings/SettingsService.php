<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\ShippingOptionCatalog;
use App\Settings\SettingDefinition;
use App\Settings\SettingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsService
{
    public function __construct(
        private readonly WordpressShippingDataLoader $geography,
        private readonly ShippingOptionCatalog $shippingOptions,
    ) {}

    public function get(string $key, mixed $default = null, ?string $group = null): mixed
    {
        $definition = SettingRegistry::get($key);
        $setting = Setting::query()
            ->where('key', $definition->key)
            ->where('group', $group ?? $definition->group)
            ->first();

        return $setting?->typed_value ?? ($default ?? $definition->default);
    }

    public function getDefinition(string $key): SettingDefinition
    {
        return SettingRegistry::get($key);
    }

    public function update(string $key, mixed $value, ?User $actor = null): Setting
    {
        $definition = SettingRegistry::get($key);
        $normalized = $this->normalize($definition, $value);

        $setting = DB::transaction(function () use ($definition, $normalized): Setting {
            return Setting::query()->updateOrCreate(
                ['group' => $definition->group, 'key' => $definition->key],
                [
                    'value' => $normalized,
                    'type' => $definition->type,
                    'is_public' => false,
                ],
            );
        });

        Log::info('settings.updated', [
            'actor_user_id' => $actor?->getKey() ?? auth()->id(),
            'setting_key' => $definition->key,
            'group' => $definition->group,
        ]);

        return $setting;
    }

    public function canManage(Setting $setting): bool
    {
        return SettingRegistry::has($setting->key)
            && SettingRegistry::get($setting->key)->group === $setting->group;
    }

    private function normalize(SettingDefinition $definition, mixed $value): mixed
    {
        $validated = Validator::make(
            ['value' => $value],
            ['value' => $definition->rules],
        )->validate()['value'];

        $normalized = match ($definition->type) {
            'integer', 'money' => $validated === null ? null : (int) $validated,
            'boolean' => (bool) $validated,
            'json' => $validated,
            default => is_string($validated) ? trim($validated) : $validated,
        };

        $this->assertShippingSetting($definition->key, $normalized);

        return $normalized;
    }

    private function assertShippingSetting(string $key, mixed $value): void
    {
        if ($key === 'shipping.origin_province_id' && $value !== null && $this->geography->provinceName((int) $value) === null) {
            throw ValidationException::withMessages(['value' => 'استان مبدأ ارسال نامعتبر است.']);
        }

        if ($key === 'shipping.origin_city_id' && $value !== null) {
            $province = (int) $this->get('shipping.origin_province_id', 0);
            if (! $this->geography->cityBelongsToProvince((int) $value, $province)) {
                throw ValidationException::withMessages(['value' => 'شهر مبدأ با استان مبدأ همخوانی ندارد.']);
            }
        }

        if ($key !== 'shipping.packages') {
            return;
        }

        $codes = array_keys($this->shippingOptions->packageSizes());
        foreach ($value as $package) {
            if (! is_array($package) || blank($package['id'] ?? null) || blank($package['name'] ?? null) || ! is_numeric($package['capacity_volume'] ?? null) || (float) $package['capacity_volume'] <= 0 || ! is_numeric($package['max_weight'] ?? null) || (float) $package['max_weight'] <= 0 || ! in_array((int) ($package['code'] ?? 0), $codes, true)) {
                throw ValidationException::withMessages(['value' => 'تنظیم بسته‌بندی نامعتبر است.']);
            }
        }
    }
}

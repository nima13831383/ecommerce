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

        if ($setting !== null) {
            return $setting->typed_value;
        }

        return $default ?? $definition->default;
    }

    public function getDefinition(string $key): SettingDefinition
    {
        return SettingRegistry::get($key);
    }

    public function update(string $key, mixed $value, ?User $actor = null): Setting
    {
        $definition = SettingRegistry::get($key);
        $normalized = $this->normalize($definition, $value);
        $previousMode = $key === 'shipping.mode' ? $this->get('shipping.mode') : null;

        $setting = DB::transaction(function () use ($definition, $normalized, $previousMode): Setting {
            $setting = Setting::query()->updateOrCreate(
                ['group' => $definition->group, 'key' => $definition->key],
                [
                    'value' => $normalized,
                    'type' => $definition->type,
                    'is_public' => false,
                ],
            );

            if ($definition->key === 'shipping.mode' && $normalized === 'calculator' && $previousMode !== 'calculator') {
                $this->assertCalculatorConfiguration();
            }

            return $setting;
        });

        Log::info('settings.updated', [
            'actor_user_id' => $actor?->getKey() ?? auth()->id(),
            'setting_key' => $definition->key,
            'group' => $definition->group,
        ]);

        return $setting;
    }

    /**
     * Ensure every registered core key has a row without changing existing values.
     *
     * @return array{added: array<int, string>, existing: array<int, string>}
     */
    public function sync(bool $dryRun = false): array
    {
        $added = [];
        $existing = [];
        $now = now();

        foreach (SettingRegistry::coreDefinitions() as $definition) {
            $query = Setting::query()
                ->where('group', $definition->group)
                ->where('key', $definition->key);

            if ($query->exists()) {
                $existing[] = $definition->key;

                continue;
            }

            $added[] = $definition->key;

            if (! $dryRun) {
                Setting::query()->insertOrIgnore([
                    'group' => $definition->group,
                    'key' => $definition->key,
                    'value' => $this->serializeValue($definition->default),
                    'type' => $definition->type,
                    'is_public' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return ['added' => $added, 'existing' => $existing];
    }

    /**
     * @return array{registered: int, persisted: int, missing: array<int, string>, unknown: array<int, array{group: string, key: string}>, needs_configuration: array<int, string>}
     */
    public function status(): array
    {
        $definitions = SettingRegistry::coreDefinitions();
        $rows = Setting::query()->get(['group', 'key']);
        $persistedKeys = [];

        foreach ($rows as $row) {
            $persistedKeys[$row->group.':'.$row->key] = true;
        }

        $missing = [];
        foreach ($definitions as $definition) {
            if (! isset($persistedKeys[$definition->group.':'.$definition->key])) {
                $missing[] = $definition->key;
            }
        }

        $unknown = $rows
            ->reject(fn (Setting $row): bool => SettingRegistry::has($row->key)
                && SettingRegistry::get($row->key)->group === $row->group)
            ->map(fn (Setting $row): array => ['group' => $row->group, 'key' => $row->key])
            ->values()
            ->all();

        $needsConfiguration = [];
        if ($this->get('shipping.mode') === 'calculator') {
            if ($this->get('shipping.origin_province_id') === null) {
                $needsConfiguration[] = 'shipping.origin_province_id';
            }
            if ($this->get('shipping.origin_city_id') === null) {
                $needsConfiguration[] = 'shipping.origin_city_id';
            }
            if ($this->get('shipping.packages') === []) {
                $needsConfiguration[] = 'shipping.packages';
            }
        }

        return [
            'registered' => count($definitions),
            'persisted' => count(array_filter($definitions, fn (SettingDefinition $definition): bool => isset($persistedKeys[$definition->group.':'.$definition->key]))),
            'missing' => $missing,
            'unknown' => $unknown,
            'needs_configuration' => $needsConfiguration,
        ];
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

    private function assertCalculatorConfiguration(): void
    {
        $province = $this->get('shipping.origin_province_id');
        $city = $this->get('shipping.origin_city_id');
        $packages = $this->get('shipping.packages', []);

        if ($province === null || $city === null || $packages === []) {
            throw ValidationException::withMessages([
                'value' => 'برای حالت محاسبه‌گر، استان، شهر و حداقل یک بسته‌بندی باید تنظیم شود.',
            ]);
        }
    }

    private function serializeValue(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
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

<?php

namespace App\Services\Settings;

use App\Models\Setting;
use App\Models\User;
use App\Settings\SettingDefinition;
use App\Settings\SettingRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SettingsService
{
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

        return match ($definition->type) {
            'integer', 'money' => $validated === null ? null : (int) $validated,
            'boolean' => (bool) $validated,
            'json' => $validated,
            default => is_string($validated) ? trim($validated) : $validated,
        };
    }
}

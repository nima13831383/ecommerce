<?php

namespace App\Filament\Resources\Settings\Concerns;

use App\Settings\SettingDefinition;
use App\Settings\SettingRegistry;

trait HandlesSettingValue
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $raw = $data['value'] ?? null;
        $type = $data['type'] ?? 'string';
        $definition = $this->settingDefinition($data);

        $data['value_string'] = $type === 'string' && ! $definition?->secret ? (string) $raw : null;
        $data['value_secret'] = null;
        $data['value_text'] = $type === 'text' ? (string) $raw : null;
        $data['value_number'] = in_array($type, ['integer', 'float', 'money'], true) ? $raw : null;
        $data['value_boolean'] = filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        $data['value_json'] = is_array($d = json_decode((string) $raw, true))
            ? json_encode($d, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            : '';

        return $data;
    }

    protected function normalizeValue(array $data): array
    {
        $definition = $this->settingDefinition($data);

        if ($definition?->secret) {
            $secret = $data['value_secret'] ?? null;
            $data['preserve_secret'] = blank($secret);
            $data['value'] = blank($secret) ? null : $secret;
        } else {
            $data['value'] = match ($data['type'] ?? 'string') {
                'string' => $data['value_string'] ?? null,
                'text' => $data['value_text'] ?? null,
                'integer', 'money' => blank($data['value_number'] ?? null) ? null : (string) (int) $data['value_number'],
                'float' => blank($data['value_number'] ?? null) ? null : (string) (float) $data['value_number'],
                'boolean' => ! empty($data['value_boolean']) ? '1' : '0',
                'json' => $this->decodeJson($data['value_json'] ?? ''),
                default => null,
            };
        }

        unset(
            $data['value_string'],
            $data['value_text'],
            $data['value_number'],
            $data['value_boolean'],
            $data['value_json'],
            $data['value_secret'],
        );

        return $data;
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function settingDefinition(array $data): ?SettingDefinition
    {
        $key = $data['key'] ?? $this->record?->key;

        return is_string($key) && SettingRegistry::has($key)
            ? SettingRegistry::get($key)
            : null;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeValue($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeValue($data);
    }
}

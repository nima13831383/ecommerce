<?php

namespace App\Settings;

use App\Exceptions\UnknownSettingException;
use Illuminate\Validation\Rule;

final class SettingRegistry
{
    /** @return array<string, SettingDefinition> */
    public static function definitions(): array
    {
        return [
            'default_tax_class_id' => new SettingDefinition(
                key: 'default_tax_class_id',
                group: 'tax',
                type: 'integer',
                default: null,
                label: 'کلاس مالیاتی پیش‌فرض',
                rules: [
                    'nullable',
                    'integer',
                    Rule::exists('tax_classes', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
            ),
        ];
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function get(string $key): SettingDefinition
    {
        return self::definitions()[$key]
            ?? throw new UnknownSettingException("Unknown setting key: {$key}");
    }
}

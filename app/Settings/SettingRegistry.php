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
            'shipping.mode' => new SettingDefinition('shipping.mode', 'shipping', 'string', 'calculator', 'روش محاسبه هزینه ارسال', ['required', Rule::in(['calculator', 'fixed', 'free'])]),
            'shipping.origin_province_id' => new SettingDefinition('shipping.origin_province_id', 'shipping', 'integer', null, 'استان مبدأ', ['nullable', 'integer']),
            'shipping.origin_city_id' => new SettingDefinition('shipping.origin_city_id', 'shipping', 'integer', null, 'شهر مبدأ', ['nullable', 'integer']),
            'shipping.fixed_rate_amount' => new SettingDefinition('shipping.fixed_rate_amount', 'shipping', 'money', 0, 'مبلغ نرخ ثابت', ['required', 'integer', 'min:0']),
            'shipping.packages' => new SettingDefinition('shipping.packages', 'shipping', 'json', [], 'بسته‌بندی‌ها / کارتن‌ها', ['array']),
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

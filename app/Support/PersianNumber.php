<?php

namespace App\Support;

final class PersianNumber
{
    private const TO_PERSIAN = [
        '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹',
        '٠' => '۰', '١' => '۱', '٢' => '۲', '٣' => '۳', '٤' => '۴',
        '٥' => '۵', '٦' => '۶', '٧' => '۷', '٨' => '۸', '٩' => '۹',
    ];

    public static function digits(string|int|float|null $value): string
    {
        return strtr((string) ($value ?? ''), self::TO_PERSIAN);
    }

    public static function integer(int|float|string|null $value): string
    {
        return self::digits(number_format((int) ($value ?? 0)));
    }

    public static function money(int|float|string|null $amount, string $currency = 'ریال'): string
    {
        return self::integer($amount).' '.$currency;
    }

    public static function percentage(int|float|string|null $value): string
    {
        return self::digits($value).'٪';
    }
}

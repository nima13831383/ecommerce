<?php

namespace App\Support;

use InvalidArgumentException;

final class IranianMobileNumber
{
    public static function normalize(string $value): string
    {
        $digits = strtr(trim($value), [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $digits = preg_replace('/[\s\-()]/u', '', $digits) ?? '';

        if (str_starts_with($digits, '+98')) {
            $digits = '0'.substr($digits, 3);
        } elseif (str_starts_with($digits, '98')) {
            $digits = '0'.substr($digits, 2);
        }

        if (! preg_match('/^09\d{9}$/', $digits)) {
            throw new InvalidArgumentException('The mobile number must be a valid Iranian mobile number.');
        }

        return $digits;
    }
}

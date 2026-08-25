<?php

namespace App\Support;

class SafeMetadata
{
    private const SENSITIVE_KEY_PARTS = [
        'secret',
        'api_key',
        'token',
        'credential',
        'password',
        'card',
        'cvv',
        'pan',
        'authorization',
    ];

    public static function format(array $metadata): string
    {
        return json_encode(self::redact($metadata), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—';
    }

    private static function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && self::isSensitiveKey($key)) {
            return '[اطلاعات حساس حذف شد]';
        }

        if (! is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = self::redact($childValue, (string) $childKey);
        }

        return $redacted;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $part) {
            if (str_contains($normalized, $part)) {
                return true;
            }
        }

        return false;
    }
}

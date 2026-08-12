<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public'];

    protected $casts = ['is_public' => 'boolean'];

    /**
     * فقط mutator (set) — بدون get تا با KeyValue/فرم تداخل نکند.
     */
    protected function value(): Attribute
    {
        return Attribute::set(fn(mixed $value): ?string => match (true) {
            is_null($value)  => null,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value)  => $value ? '1' : '0',
            default          => (string) $value,
        });
    }

    /** مقدار تایپ‌شده برای منطق برنامه */
    protected function typedValue(): Attribute
    {
        return Attribute::get(function (): mixed {
            $raw = $this->attributes['value'] ?? null;

            return match ($this->type) {
                'boolean' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
                'integer' => (int) $raw,
                'float'   => (float) $raw,
                'json'    => is_array($d = json_decode((string) $raw, true)) ? $d : [],
                default   => $raw,
            };
        });
    }

    public static function getValue(string $key, ?string $group = null, mixed $default = null): mixed
    {
        return static::query()
            ->where('key', $key)
            ->when($group, fn($q) => $q->where('group', $group))
            ->first()
            ?->typed_value ?? $default;
    }
}

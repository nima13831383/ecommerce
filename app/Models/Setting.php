<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'is_public'];
    protected $casts = ['is_public' => 'boolean'];

    public function getTypedValueAttribute(): mixed
    {
        return match ($this->type) {
            'int'  => (int) $this->value,
            'bool' => (bool) $this->value,
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}

<?php

namespace App\Models;

use App\Enums\TaxType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxClass extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'type',
        'value',
        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => TaxType::class,
            'value' => 'decimal:3',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

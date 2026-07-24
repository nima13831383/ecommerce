<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// app/Models/TaxClass.php
class TaxClass extends Model
{
    protected $fillable = ['name', 'slug', 'is_default'];
    protected $casts = ['is_default' => 'boolean'];

    public function rates(): HasMany
    {
        return $this->hasMany(TaxRate::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

// a pp/Models/TaxRate.php
class TaxRate extends Model
{
    protected $fillable = [
        'tax_class_id',
        'country',
        'state',
        'city',
        'name',
        'rate',
        'compound',
        'shipping_taxable',
        'priority',
    ];
    protected $casts = [
        'rate'             => 'decimal:3',
        'compound'         => 'boolean',
        'shipping_taxable' => 'boolean',
    ];

    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }
}

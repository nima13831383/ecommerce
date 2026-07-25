<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// app/Models/ProductVariation.php
class ProductVariation extends Model
{
    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'manage_stock',
        'stock_quantity',
        'stock_status',
        'weight',
        'image',
        'is_active',
        'is_dismissed',
    ];

    protected $casts = [
        'price'          => 'decimal:0',
        'sale_price'     => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'manage_stock'   => 'boolean',
        'is_active'      => 'boolean',
        'weight'         => 'decimal:2',
        'is_dismissed' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'attribute_value_product_variation',
            'product_variation_id',
            'attribute_value_id'
        );
    }
    // app/Models/ProductVariation.php
    // public function attributeValues()
    // {
    //     return $this->belongsToMany(
    //         AttributeValue::class,
    //         'attribute_value_product_variation'
    //     );
    // }

    // ── قیمت مؤثر با در نظر گرفتن حراج ──────────────
    public function getEffectivePriceAttribute(): float
    {
        if ($this->sale_price !== null && $this->isOnSale()) {
            return (float) $this->sale_price;
        }
        return (float) $this->price;
    }

    public function isOnSale(): bool
    {
        if ($this->sale_price === null) {
            return false;
        }
        $now = now();
        if ($this->sale_starts_at && $now->lt($this->sale_starts_at)) {
            return false;
        }
        if ($this->sale_ends_at && $now->gt($this->sale_ends_at)) {
            return false;
        }
        return true;
    }

    // ── برچسب خوانا: "قرمز / L" ─────────────────────
    public function getLabelAttribute(): string
    {
        return $this->attributeValues->pluck('value')->implode(' / ') ?: 'Variation';
    }
}

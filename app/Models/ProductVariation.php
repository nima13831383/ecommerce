<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    // public function getLabelAttribute(): string
    // {
    //     return $this->attributeValues->pluck('value')->implode(' / ') ?: 'Variation';
    // }
    public function getLabelAttribute(): string
    {
        $this->loadMissing('attributeValues.attribute');

        return $this->attributeValues
            ->sortBy(fn($v) => $v->attribute->sort_order)
            ->map(fn($v) => "{$v->attribute->name}: {$v->value}")
            ->implode(' / ') ?: 'Variation';
    }

    /** @return BelongsToMany<AttributeValue, $this> */
    // public function attributeValues(): BelongsToMany
    // {
    //     return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variation');
    // }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variation_id');
    }
    // app/Models/ProductVariation.php
    // protected static function booted(): void
    // {
    //     static::saving(function (self $variation): void {
    //         if ($variation->manage_stock) {
    //             $variation->stock_status = $variation->stock_quantity > 0
    //                 ? ($variation->stock_status === 'on_backorder' ? 'on_backorder' : 'in_stock')
    //                 : 'out_of_stock';
    //         }
    //     });
    // }
    protected static function booted(): void
    {
        static::saving(function (self $variation): void {
            if (! $variation->manage_stock) {
                return;
            }

            if ($variation->stock_quantity > 0) {
                // on_backorder را دست‌نخورده بگذار، تصمیم دستی ادمین است
                if ($variation->stock_status !== 'on_backorder') {
                    $variation->stock_status = 'in_stock';
                }

                return;
            }

            $variation->stock_status = 'out_of_stock';
        });
    }
}

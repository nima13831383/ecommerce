<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->line_key ??= self::makeLineKey(
                (int) $item->product_id,
                $item->product_variation_id === null ? null : (int) $item->product_variation_id,
            );
        });
    }

    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variation_id',
        'line_key',
        'quantity',
        'unit_price',
        'line_total',
        'options',
    ];

    protected $casts = [
        'unit_price' => 'integer',
        'line_total' => 'integer',
        'options' => 'array',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'product_variation_id');
    }

    public static function makeLineKey(int $productId, ?int $variationId): string
    {
        return "product:{$productId}:variation:".($variationId ?? 'none');
    }
}

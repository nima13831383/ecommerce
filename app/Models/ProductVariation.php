<?php

namespace App\Models;

use App\Services\Catalog\ProductPriceResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariation extends Model
{
    protected $fillable = ['product_id', 'combination_signature', 'sku', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at', 'manage_stock', 'weight', 'volume', 'image', 'is_active', 'is_dismissed'];

    protected $casts = ['price' => 'decimal:0', 'sale_price' => 'decimal:0', 'sale_starts_at' => 'datetime', 'sale_ends_at' => 'datetime', 'manage_stock' => 'boolean', 'is_active' => 'boolean', 'weight' => 'decimal:2', 'volume' => 'decimal:6', 'is_dismissed' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product_variation', 'product_variation_id', 'attribute_value_id');
    }

    public function getEffectivePriceAttribute(): int
    {
        return app(ProductPriceResolver::class)->effectivePriceForVariation($this);
    }

    public function isOnSale(): bool
    {
        return app(ProductPriceResolver::class)->isVariationOnSale($this);
    }

    public function getLabelAttribute(): string
    {
        $this->loadMissing('attributeValues.attribute');

        return $this->attributeValues->sortBy(fn ($value) => $value->attribute->sort_order)->map(fn ($value) => "{$value->attribute->name}: {$value->value}")->implode(' / ') ?: 'Variation';
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variation_id');
    }
}

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
    ];

    protected $casts = [
        'price'          => 'decimal:0',
        'sale_price'     => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'manage_stock'   => 'boolean',
        'is_active'      => 'boolean',
        'weight'         => 'decimal:2',
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
}

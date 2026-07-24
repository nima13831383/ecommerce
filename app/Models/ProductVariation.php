<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    use SoftDeletes;

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
        'low_stock_threshold',
        'weight',
        'length',
        'width',
        'height',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'price'          => 'decimal:0',
        'sale_price'     => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'manage_stock'   => 'boolean',
        'is_default'     => 'boolean',
        'is_active'      => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function attributeValues()
    {
        return $this->belongsToMany(AttributeValue::class);
    }
}

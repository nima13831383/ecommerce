<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variation_id',
        'product_name',
        'variation_label',
        'sku',
        'quantity',
        'unit_price',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'tax_rate',
        'total',
        'meta',
    ];

    protected $casts = [
        'unit_price'      => 'decimal:0',
        'subtotal'        => 'decimal:0',
        'discount_amount' => 'decimal:0',
        'tax_amount'      => 'decimal:0',
        'total'           => 'decimal:0',
        'meta'            => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function productVariation()
    {
        return $this->belongsTo(ProductVariation::class);
    }
}

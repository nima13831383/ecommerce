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
        'sku',
        'variation_attributes',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
    ];

    protected $casts = [
        'variation_attributes' => 'array',
        'unit_price'           => 'decimal:0',
        'discount_amount'      => 'decimal:0',
        'tax_amount'           => 'decimal:0',
        'line_total'           => 'decimal:0',
    ];
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function variation()
    {
        return $this->belongsTo(ProductVariation::class);
    }
}

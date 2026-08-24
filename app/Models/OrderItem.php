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
        'line_subtotal',
        'discount_amount',
        'tax_amount',
        'tax_snapshot',
        'line_total',
        'inventory_reservation_id',
    ];

    protected $casts = [
        'variation_attributes' => 'array',
        'unit_price' => 'integer',
        'line_subtotal' => 'integer',
        'discount_amount' => 'integer',
        'tax_amount' => 'integer',
        'tax_snapshot' => 'array',
        'line_total' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }

    public function inventoryReservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class);
    }
}

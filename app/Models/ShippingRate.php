<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_zone_id',
        'shipping_method_id',
        'shipping_class_id',
        'base_cost',
        'cost_per_kg',
        'cost_per_item',
        'min_order_total',
        'max_order_total',
        'min_weight',
        'max_weight',
        'free_over',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'base_cost'       => 'decimal:0',
        'cost_per_kg'     => 'decimal:0',
        'cost_per_item'   => 'decimal:0',
        'min_order_total' => 'decimal:0',
        'max_order_total' => 'decimal:0',
        'min_weight'      => 'decimal:3',
        'max_weight'      => 'decimal:3',
        'free_over'       => 'decimal:0',
        'is_active'       => 'boolean',
    ];

    public function method()
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }
    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }

    public function shippingClass(): BelongsTo
    {
        return $this->belongsTo(ShippingClass::class, 'shipping_class_id');
    }
}

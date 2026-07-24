<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShippingRate extends Model
{
    protected $fillable = [
        'shipping_method_id',
        'region_id',
        'zip_code',
        'cost',
        'min_amount',
        'max_amount',
    ];

    protected $casts = [
        'cost'       => 'decimal:0',
        'min_amount' => 'decimal:0',
        'max_amount' => 'decimal:0',
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

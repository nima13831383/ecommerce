<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ShippingMethod extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'carrier',
        'logo',
        'calc_type',
        'requires_tracking',
        'is_pickup',
        'is_cod_available',
        'estimated_days_min',
        'estimated_days_max',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'requires_tracking' => 'boolean',
        'is_pickup'         => 'boolean',
        'is_cod_available'  => 'boolean',
        'is_active'         => 'boolean',
    ];
    public function zone()
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}

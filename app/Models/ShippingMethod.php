<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class ShippingMethod extends Model
{
    protected $fillable = [
        'shipping_zone_id',
        'name',
        'type',
        'cost',
        'is_enabled',
        'min_amount',
        'max_amount',
        'settings',
    ];

    protected $casts = [
        'cost'       => 'decimal:0',
        'min_amount' => 'decimal:0',
        'max_amount' => 'decimal:0',
        'is_enabled' => 'boolean',
        'settings'   => 'array',
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

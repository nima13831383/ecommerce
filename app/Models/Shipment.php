<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shipment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'shipment_number',
        'order_id',
        'shipping_method_id',
        'method_name',
        'carrier',
        'status',
        'tracking_code',
        'tracking_url',
        'shipping_cost',
        'weight',
        'shipping_address',
        'notes',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'shipping_cost'    => 'decimal:0',
        'weight'           => 'decimal:3',
        'shipping_address' => 'array',
        'shipped_at'       => 'datetime',
        'delivered_at'     => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class, 'shipping_method_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }
}

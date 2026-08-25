<?php

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryReservation extends Model
{
    protected $fillable = ['inventory_owner_type', 'inventory_owner_id', 'quantity', 'status', 'reference_type', 'reference_id', 'expires_at', 'committed_at', 'released_at', 'metadata'];

    protected $casts = ['status' => InventoryReservationStatus::class, 'expires_at' => 'datetime', 'committed_at' => 'datetime', 'released_at' => 'datetime', 'metadata' => 'array'];

    public function inventoryOwner(): MorphTo
    {
        return $this->morphTo();
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'reference_id');
    }
}

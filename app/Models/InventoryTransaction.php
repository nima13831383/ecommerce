<?php

namespace App\Models;

use App\Enums\InventoryOperation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InventoryTransaction extends Model
{
    protected $fillable = ['inventory_owner_type', 'inventory_owner_id', 'operation', 'quantity_delta', 'quantity_before', 'quantity_after', 'reference_type', 'reference_id', 'reason', 'metadata', 'created_by'];

    protected $casts = ['operation' => InventoryOperation::class, 'metadata' => 'array'];

    public function inventoryOwner(): MorphTo
    {
        return $this->morphTo();
    }
}

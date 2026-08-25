<?php

namespace App\Filament\Resources\InventoryTransactions\Pages;

use App\Filament\Resources\InventoryTransactions\InventoryTransactionResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ViewInventoryTransaction extends ViewRecord
{
    protected static string $resource = InventoryTransactionResource::class;

    protected static ?string $title = 'جزئیات تراکنش موجودی';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'inventoryReservation.orderItem.order',
            'createdBy:id,name',
            'inventoryOwner' => fn (MorphTo $relation): MorphTo => $relation->morphWith([Product::class => [], ProductVariation::class => ['product']]),
        ]);
    }
}

<?php

namespace App\Filament\Resources\InventoryReservations\Pages;

use App\Filament\Resources\InventoryReservations\InventoryReservationResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ViewInventoryReservation extends ViewRecord
{
    protected static string $resource = InventoryReservationResource::class;

    protected static ?string $title = 'جزئیات رزرو موجودی';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'orderItem.order',
            'inventoryOwner' => fn (MorphTo $relation): MorphTo => $relation->morphWith([
                Product::class => [],
                ProductVariation::class => ['product'],
            ]),
        ]);
    }
}

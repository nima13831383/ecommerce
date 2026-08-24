<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Concerns\ConfiguresProductVariations;
use App\Filament\Resources\Products\ProductResource;
// class CreateProduct extends CreateRecord
// {
//     protected static string $resource = ProductResource::class;
// }

// CreateProduct.php
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    use ConfiguresProductVariations;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->extractVariationState($data);
    }

    protected function afterCreate(): void
    {
        $this->persistProductInventory($this->record, true);
        $this->persistVariations($this->record);
    }
}

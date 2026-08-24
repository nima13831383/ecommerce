<?php

// Pages/EditProduct.php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Concerns\ConfiguresProductVariations;
use App\Filament\Resources\Products\ProductResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

// class EditProduct extends EditRecord
// {
//     protected static string $resource = ProductResource::class;

//     protected function getHeaderActions(): array
//     {
//         return [
//             DeleteAction::make(),
//             ForceDeleteAction::make(),
//             RestoreAction::make(),
//         ];
//     }
// }

// EditProduct.php
class EditProduct extends EditRecord
{
    use ConfiguresProductVariations;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydrateVariationState($data, $this->record);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractVariationState($data);
    }

    protected function afterSave(): void
    {
        $this->persistProductInventory($this->record, false);
        $this->persistVariations($this->record);
    }
}

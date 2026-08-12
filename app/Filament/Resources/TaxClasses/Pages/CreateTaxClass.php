<?php

namespace App\Filament\Resources\TaxClasses\Pages;

use App\Filament\Resources\TaxClasses\TaxClassResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaxClass extends CreateRecord
{
    protected static string $resource = TaxClassResource::class;
}

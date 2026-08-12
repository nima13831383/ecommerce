<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\Settings\Concerns\HandlesSettingValue;

class CreateSetting extends CreateRecord
{
    use HandlesSettingValue;
    protected static string $resource = SettingResource::class;
}

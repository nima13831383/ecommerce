<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\Concerns\HandlesSettingValue;
use App\Filament\Resources\Settings\SettingResource;
use App\Services\Settings\SettingsService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSetting extends EditRecord
{
    use HandlesSettingValue;

    protected static string $resource = SettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(SettingsService::class)->update(
            key: $record->key,
            value: $data['value'] ?? null,
            actor: auth()->user(),
        );
    }
}

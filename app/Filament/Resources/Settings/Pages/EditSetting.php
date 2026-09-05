<?php

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\Concerns\HandlesSettingValue;
use App\Filament\Resources\Settings\SettingResource;
use App\Services\Settings\SettingsService;
use App\Settings\SettingDefinition;
use App\Settings\SettingRegistry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

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
        $definition = SettingRegistry::get($record->key);
        if ($definition->secret && blank($data['value'] ?? null)) {
            return $record;
        }

        try {
            return app(SettingsService::class)->update(
                key: $record->key,
                value: $data['value'] ?? null,
                actor: auth()->user(),
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['value'][0] ?? 'مقدار تنظیم نامعتبر است.';

            throw ValidationException::withMessages([
                'data.'.$this->valueInput($definition) => $message,
            ]);
        }
    }

    private function valueInput(SettingDefinition $definition): string
    {
        if ($definition->secret) {
            return 'value_secret';
        }

        return match ($definition->type) {
            'integer', 'float', 'money' => 'value_number',
            'boolean' => 'value_boolean',
            'json' => 'value_json',
            'text' => 'value_text',
            default => 'value_string',
        };
    }
}

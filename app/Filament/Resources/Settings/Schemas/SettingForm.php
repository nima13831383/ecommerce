<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\TaxClass;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group')->label('گروه')->disabled()->dehydrated(false),
            TextInput::make('key')->label('کلید داخلی')->disabled()->dehydrated(false),
            TextInput::make('type')->label('نوع مقدار')->disabled()->dehydrated(),
            Select::make('value_number')
                ->label('کلاس مالیاتی پیش‌فرض')
                ->options(fn (): array => TaxClass::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->nullable()
                ->helperText('این مقدار برای محصولاتی استفاده می‌شود که کلاس مالیاتی جداگانه ندارند.')
                ->required(false),
        ])->columns(2);
    }
}

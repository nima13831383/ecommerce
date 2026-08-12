<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;
use App\Filament\Resources\Settings\Schemas\SettingForm;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'site-settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'تنظیم';

    protected static ?string $pluralModelLabel = 'تنظیمات';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('group')
            ->defaultSort('key')
            ->columns([
                TextColumn::make('group')
                    ->label('گروه')
                    ->badge()
                    ->sortable(),

                TextColumn::make('key')
                    ->label('کلید')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('value')
                    ->label('مقدار')
                    ->limit(50)
                    ->wrap()
                    ->tooltip(fn(Setting $record) => is_string($record->value) ? $record->value : null),

                TextColumn::make('type')
                    ->label('نوع')->badge()
                    ->formatStateUsing(fn(?string $state) => SettingForm::TYPES[$state] ?? $state),

                IconColumn::make('is_public')
                    ->label('عمومی')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('آخرین تغییر')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('گروه')
                    ->options(fn() => Setting::query()
                        ->distinct()
                        ->pluck('group', 'group')
                        ->all()),

                SelectFilter::make('type')->label('نوع')->options(SettingForm::TYPES),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => \App\Filament\Resources\Settings\Pages\ListSettings::route('/'),
            'create' => \App\Filament\Resources\Settings\Pages\CreateSetting::route('/create'),
            'edit'   => \App\Filament\Resources\Settings\Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}

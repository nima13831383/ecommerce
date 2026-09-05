<?php

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Schemas\SettingForm;
use App\Models\Setting;
use App\Settings\SettingRegistry;
use App\Support\JalaliDate;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'site-settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'تنظیمات';

    protected static ?string $modelLabel = 'تنظیم';

    protected static ?string $pluralModelLabel = 'تنظیمات';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete($record = null): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultGroup('group')
            ->defaultSort('key')
            ->columns([
                TextColumn::make('group')->label('گروه')->badge()->sortable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'catalog' => 'فروشگاه',
                        'blog' => 'مجله',
                        'shipping' => 'ارسال',
                        'tax' => 'مالیات',
                        'payment' => 'پرداخت',
                        default => $state,
                    }),
                TextColumn::make('key')->label('کلید داخلی')->searchable()->copyable(),
                TextColumn::make('value_state')
                    ->label('وضعیت مقدار')
                    ->state(function (Setting $record): string {
                        $definition = SettingRegistry::has($record->key)
                            ? SettingRegistry::get($record->key)
                            : null;

                        if ($definition?->secret) {
                            return filled($record->value) ? 'پیکربندی شده' : 'نیاز به تنظیم';
                        }

                        return filled($record->value) ? 'ثبت شده' : 'خالی';
                    })
                    ->badge()
                    ->color(fn (string $state): string => in_array($state, ['ثبت شده', 'پیکربندی شده'], true) ? 'success' : 'warning'),
                TextColumn::make('type')
                    ->label('نوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'integer' => 'عدد صحیح',
                        'boolean' => 'بله/خیر',
                        'json' => 'داده ساختاریافته',
                        'money' => 'مبلغ ریالی',
                        default => 'متن',
                    }),
                TextColumn::make('updated_at')->label('آخرین تغییر')->formatStateUsing(fn ($state): ?string => $state ? JalaliDate::format($state, 'Y/m/d H:i') : null)->sortable(),
            ])
            ->filters([
                SelectFilter::make('group')
                    ->label('گروه')
                    ->options(fn (): array => Setting::query()->distinct()->pluck('group', 'group')->all()),
            ])
            ->recordActions([
                EditAction::make()->label('ویرایش')->authorize('update'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSettings::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}

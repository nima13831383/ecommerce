<?php

namespace App\Filament\Resources\Settings\Schemas;

use App\Models\TaxClass;
use App\Services\Settings\SettingsService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('group')->label('گروه')->disabled()->dehydrated(false),
            TextInput::make('key')->label('کلید داخلی')->disabled()->dehydrated(false),
            TextInput::make('type')->label('نوع مقدار')->disabled()->dehydrated(),
            Select::make('value_string')
                ->label('روش محاسبه هزینه ارسال')
                ->options(['calculator' => 'محاسبه‌گر اصلی', 'fixed' => 'نرخ ثابت', 'free' => 'ارسال رایگان'])
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.mode')
                ->required(fn (Get $get): bool => $get('key') === 'shipping.mode'),
            Select::make('value_number')
                ->label('کلاس مالیاتی پیش‌فرض')
                ->visible(fn (Get $get): bool => in_array($get('key'), ['default_tax_class_id', 'shipping.origin_province_id', 'shipping.origin_city_id', 'shipping.fixed_rate_amount'], true))
                ->options(fn (Get $get): array => match ($get('key')) {
                    'default_tax_class_id' => TaxClass::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
                    'shipping.origin_province_id' => app(WordpressShippingDataLoader::class)->provinces(),
                    'shipping.origin_city_id' => app(WordpressShippingDataLoader::class)->cities((int) app(SettingsService::class)->get('shipping.origin_province_id', 0)),
                    default => [],
                })
                ->searchable()
                ->nullable()
                ->helperText('این مقدار برای محصولاتی استفاده می‌شود که کلاس مالیاتی جداگانه ندارند.')
                ->required(false),
            Textarea::make('value_json')
                ->label('بسته‌بندی‌ها / کارتن‌ها (JSON)')
                ->visible(fn (Get $get): bool => $get('key') === 'shipping.packages')
                ->helperText('هر بسته باید id، name، capacity_volume، max_weight، code و active داشته باشد.'),
        ])->columns(2);
    }
}

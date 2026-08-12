<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\{KeyValue, Select, Textarea, TextInput, Toggle};
use Filament\Schemas\Schema;

class SettingForm
{
    public const TYPES = [
        'string'  => 'متن',
        'text'    => 'متن بلند',
        'integer' => 'عدد',
        'float'   => 'عدد اعشاری',
        'boolean' => 'بله/خیر',
        'json'    => 'JSON',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('group')
                ->label('گروه')
                ->required()->default('general')->searchable()->live()
                ->options([
                    'general'  => 'عمومی',
                    'shop'     => 'فروشگاه',
                    'tax'      => 'مالیات',
                    'shipping' => 'ارسال',
                    'seo'      => 'سئو',
                    'social'   => 'شبکه‌های اجتماعی',
                ]),

            TextInput::make('key')
                ->label('کلید')
                ->required()->maxLength(255)
                ->rules(['regex:/^[a-z0-9_\.]+$/'])
                ->helperText('فقط حروف کوچک، عدد، نقطه و آندرلاین')
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn($rule, callable $get) => $rule->where('group', $get('group')),
                ),

            Select::make('type')
                ->label('نوع مقدار')
                ->required()->default('string')->live()
                ->options(self::TYPES)
                ->afterStateUpdated(fn(callable $set) => $set('value_json', []))
                ->dehydrated(),

            // ---- فیلدهای مقدار: هر کدام statePath مستقل ----
            TextInput::make('value_string')
                ->label('مقدار')->maxLength(65535)
                ->dehydrated(false)
                ->visible(fn($get) => $get('type') === 'string'),

            Textarea::make('value_text')
                ->label('مقدار')->rows(5)->columnSpanFull()
                ->dehydrated(false)
                ->visible(fn($get) => $get('type') === 'text'),

            TextInput::make('value_number')
                ->label('مقدار')->numeric()
                ->dehydrated(false)
                ->visible(fn($get) => in_array($get('type'), ['integer', 'float'], true)),

            Toggle::make('value_boolean')
                ->label('مقدار')
                ->dehydrated(false)
                ->visible(fn($get) => $get('type') === 'boolean'),

            KeyValue::make('value_json')
                ->label('مقدار')
                ->keyLabel('کلید')->valueLabel('مقدار')
                ->default([])
                ->dehydrated(false)
                ->columnSpanFull()
                ->visible(fn($get) => $get('type') === 'json'),

            Toggle::make('is_public')
                ->label('قابل دسترس در فرانت')
                ->helperText('اگر فعال باشد، این مقدار در خروجی عمومی سایت قابل استفاده است.')
                ->default(false),
        ])->columns(2);
    }
}

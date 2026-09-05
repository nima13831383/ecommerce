<?php

namespace App\Filament\Resources\TaxClasses\Schemas;

use App\Enums\TaxType;
use App\Models\TaxClass;
use App\Support\PersianNumber;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TaxClassForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('جزئیات کلاس مالیاتی')
                ->columns(2)
                ->schema(self::fields()),
        ]);
    }

    /** @return array<Component> */
    public static function fields(bool $includeSlug = true): array
    {
        $fields = [
            TextInput::make('name')
                ->label('نام')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn (?string $state, callable $set) => $set('slug', self::slugFor($state))),
        ];

        if ($includeSlug) {
            $fields[] = TextInput::make('slug')
                ->label('نامک')
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true);
        }

        return [
            ...$fields,
            Textarea::make('description')
                ->label('توضیحات')
                ->rows(2)
                ->columnSpanFull(),
            Select::make('type')
                ->label('نوع مالیات')
                ->options(TaxType::class)
                ->default(TaxType::Percent)
                ->required()
                ->native(false)
                ->live(),
            TextInput::make('value')
                ->label(fn (Get $get): string => TaxType::parse($get('type')) === TaxType::Percent ? 'نرخ مالیات' : 'مبلغ مالیات برای هر واحد')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(fn (Get $get): ?int => TaxType::parse($get('type')) === TaxType::Percent ? 100 : null)
                ->step(fn (Get $get): string => TaxType::parse($get('type')) === TaxType::Percent ? '0.001' : '1')
                ->rule(fn (Get $get): string => TaxType::parse($get('type')) === TaxType::Fixed ? 'integer' : 'decimal:0,3')
                ->suffix(fn (Get $get): ?string => TaxType::parse($get('type'))?->affix())
                ->helperText(fn (Get $get): string => TaxType::parse($get('type')) === TaxType::Percent
                    ? 'درصد از مبلغِ بدون مالیات محاسبه و به نزدیک‌ترین ریال گرد می‌شود.'
                    : 'این مبلغ به ازای هر واحد کالا، بر حسب ریال محاسبه می‌شود.'),
            Toggle::make('is_active')->label('فعال')->default(true),
            Toggle::make('is_default')
                ->label('پیش‌فرض سیستم')
                ->helperText('برای محصول بدون کلاس مالیاتیِ مشخص استفاده می‌شود.')
                ->default(false),
        ];
    }

    public static function formatValue(TaxClass $taxClass): string
    {
        if (TaxType::parse($taxClass->type) === TaxType::Percent) {
            return PersianNumber::digits(rtrim(rtrim((string) $taxClass->value, '0'), '.')).'٪';
        }

        return PersianNumber::money($taxClass->value);
    }

    public static function slugFor(?string $name): string
    {
        return str($name)->slug()->toString();
    }
}

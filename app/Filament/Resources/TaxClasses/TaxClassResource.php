<?php

namespace App\Filament\Resources\TaxClasses;

use App\Enums\TaxType;
use App\Filament\Resources\TaxClasses\Pages;
use App\Filament\Resources\TaxClasses\RelationManagers\ProductsRelationManager;
use App\Models\TaxClass;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

class TaxClassResource extends Resource
{
    protected static ?string $model = TaxClass::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $modelLabel = 'کلاس مالیاتی';

    protected static ?string $pluralModelLabel = 'کلاس‌های مالیاتی';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('جزئیات کلاس مالیاتی')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('نام')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn(?string $state, callable $set) => $set('slug', str($state)->slug()->toString())),

                    TextInput::make('slug')
                        ->label('نامک')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    Textarea::make('description')
                        ->label('توضیحات')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),

            Section::make('تنظیمات نرخ')
                ->columns(2)
                ->schema([
                    Select::make('type')
                        ->label('نوع مالیات')
                        ->options(TaxType::class)
                        ->default(TaxType::Percent)
                        ->required()
                        ->native(false)
                        ->live(),

                    TextInput::make('value')
                        ->label(fn(Get $get): string => TaxType::parse($get('type')) === TaxType::Percent
                            ? 'درصد'
                            : 'مبلغ')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->maxValue(fn(Get $get): ?int => TaxType::parse($get('type')) === TaxType::Percent
                            ? 100
                            : null)
                        ->suffix(fn(Get $get): ?string => TaxType::parse($get('type'))?->affix()),

                    Toggle::make('is_active')
                        ->label('فعال')
                        ->default(true),

                    Toggle::make('is_default')
                        ->label('پیش‌فرض سیستم')
                        ->helperText('به‌عنوان مقدار پیش‌فرض فرم محصول جدید استفاده می‌شود.')
                        ->default(false),
                ]),

        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label('نوع')
                    ->badge(),

                TextColumn::make('value')
                    ->label('نرخ')
                    ->formatStateUsing(
                        fn($state, TaxClass $record): string =>
                        TaxType::parse($record->type) === TaxType::Percent
                            ? $state . '%'
                            : number_format((float) $state) . ' ریال'
                    )
                    ->sortable(),


                IconColumn::make('is_active')
                    ->label('وضعیت')
                    ->boolean()
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('پیش‌فرض')
                    ->boolean(),

                TextColumn::make('products_count')
                    ->label('محصولات')
                    ->counts('products')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('نوع مالیات')
                    ->options(TaxType::class),

                TernaryFilter::make('is_active')
                    ->label('وضعیت'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxClasses::route('/'),
            'create' => Pages\CreateTaxClass::route('/create'),
            'edit' => Pages\EditTaxClass::route('/{record}/edit'),
        ];
    }
}

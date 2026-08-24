<?php

namespace App\Filament\Resources\TaxClasses;

use App\Filament\Resources\TaxClasses\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\TaxClasses\Schemas\TaxClassForm;
use App\Filament\Resources\TaxClasses\Tables\TaxClassesTable;
use App\Models\TaxClass;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
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
        return TaxClassForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxClassesTable::configure($table);
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

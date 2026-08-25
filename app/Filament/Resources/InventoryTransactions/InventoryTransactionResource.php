<?php

namespace App\Filament\Resources\InventoryTransactions;

use App\Filament\Resources\InventoryTransactions\Pages\ListInventoryTransactions;
use App\Filament\Resources\InventoryTransactions\Pages\ViewInventoryTransaction;
use App\Filament\Resources\InventoryTransactions\Schemas\InventoryTransactionInfolist;
use App\Filament\Resources\InventoryTransactions\Tables\InventoryTransactionsTable;
use App\Models\InventoryTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventoryTransactionResource extends Resource
{
    protected static ?string $model = InventoryTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|UnitEnum|null $navigationGroup = 'مدیریت موجودی';

    protected static ?string $navigationLabel = 'تراکنش‌های موجودی';

    protected static ?string $modelLabel = 'تراکنش موجودی';

    protected static ?string $pluralModelLabel = 'تراکنش‌های موجودی';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return InventoryTransactionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryTransactionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryTransactions::route('/'), 'view' => ViewInventoryTransaction::route('/{record}')];
    }
}

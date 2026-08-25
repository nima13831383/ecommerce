<?php

namespace App\Filament\Resources\InventoryReservations;

use App\Filament\Resources\InventoryReservations\Pages\ListInventoryReservations;
use App\Filament\Resources\InventoryReservations\Pages\ViewInventoryReservation;
use App\Filament\Resources\InventoryReservations\Schemas\InventoryReservationInfolist;
use App\Filament\Resources\InventoryReservations\Tables\InventoryReservationsTable;
use App\Models\InventoryReservation;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InventoryReservationResource extends Resource
{
    protected static ?string $model = InventoryReservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'مدیریت موجودی';

    protected static ?string $navigationLabel = 'رزروهای موجودی';

    protected static ?string $modelLabel = 'رزرو موجودی';

    protected static ?string $pluralModelLabel = 'رزروهای موجودی';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return InventoryReservationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InventoryReservationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return ['index' => ListInventoryReservations::route('/'), 'view' => ViewInventoryReservation::route('/{record}')];
    }
}

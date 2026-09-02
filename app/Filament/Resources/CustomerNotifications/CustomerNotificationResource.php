<?php

namespace App\Filament\Resources\CustomerNotifications;

use App\Filament\Resources\CustomerNotifications\Pages\ListCustomerNotifications;
use App\Filament\Resources\CustomerNotifications\Pages\ViewCustomerNotification;
use App\Filament\Resources\CustomerNotifications\Schemas\CustomerNotificationInfolist;
use App\Filament\Resources\CustomerNotifications\Tables\CustomerNotificationsTable;
use App\Models\CustomerNotification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CustomerNotificationResource extends Resource
{
    protected static ?string $model = CustomerNotification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected static string|UnitEnum|null $navigationGroup = 'Products';

    protected static ?string $navigationLabel = 'اعلان‌های مشتریان';

    protected static ?string $modelLabel = 'اعلان مشتری';

    protected static ?string $pluralModelLabel = 'اعلان‌های مشتریان';

    protected static ?int $navigationSort = 8;

    public static function infolist(Schema $schema): Schema
    {
        return CustomerNotificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CustomerNotificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCustomerNotifications::route('/'),
            'view' => ViewCustomerNotification::route('/{record}'),
        ];
    }
}

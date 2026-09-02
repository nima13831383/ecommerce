<?php

namespace App\Filament\Resources\CustomerNotifications\Pages;

use App\Filament\Resources\CustomerNotifications\CustomerNotificationResource;
use Filament\Resources\Pages\ListRecords;

class ListCustomerNotifications extends ListRecords
{
    protected static string $resource = CustomerNotificationResource::class;
}

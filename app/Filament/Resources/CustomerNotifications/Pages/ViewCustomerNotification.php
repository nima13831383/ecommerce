<?php

namespace App\Filament\Resources\CustomerNotifications\Pages;

use App\Enums\CustomerNotificationStatus;
use App\Filament\Resources\CustomerNotifications\CustomerNotificationResource;
use App\Models\CustomerNotification;
use App\Services\Notifications\CustomerNotificationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerNotification extends ViewRecord
{
    protected static string $resource = CustomerNotificationResource::class;

    protected static ?string $title = 'جزئیات اعلان مشتری';

    protected function resolveRecord(int|string $key): CustomerNotification
    {
        return parent::resolveRecord($key)->load(['order:id,order_number', 'user:id,name,email,mobile']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('تلاش مجدد')
                ->color('warning')
                ->authorize('retry')
                ->visible(fn (CustomerNotification $record): bool => $record->status === CustomerNotificationStatus::Failed)
                ->requiresConfirmation()
                ->action(function (CustomerNotification $record, CustomerNotificationService $notifications): void {
                    $notifications->retry($record);
                    Notification::make()->title('اعلان برای تلاش مجدد در صف قرار گرفت.')->success()->send();
                }),
        ];
    }
}

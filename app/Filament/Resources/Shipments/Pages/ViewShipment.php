<?php

namespace App\Filament\Resources\Shipments\Pages;

use App\Enums\ShipmentStatus;
use App\Filament\Resources\Shipments\ShipmentResource;
use App\Models\Shipment;
use App\Services\Fulfillment\ShipmentService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewShipment extends ViewRecord
{
    protected static string $resource = ShipmentResource::class;

    protected static ?string $title = 'جزئیات مرسوله';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'order:id,order_number,status,payment_status,shipping_address,shipping_snapshot',
            'statusHistories.user:id,name',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->transitionAction(ShipmentStatus::Ready, 'آماده‌سازی مرسوله', 'markReady'),
            $this->trackingAction(),
            $this->transitionAction(ShipmentStatus::Shipped, 'ثبت ارسال', 'markShipped'),
            $this->transitionAction(ShipmentStatus::Delivered, 'ثبت تحویل', 'markDelivered'),
            $this->transitionAction(ShipmentStatus::Cancelled, 'لغو مرسوله', 'cancel', true),
        ];
    }

    private function transitionAction(ShipmentStatus $to, string $label, string $ability, bool $danger = false): Action
    {
        return Action::make("transition_{$to->value}")
            ->label($label)
            ->color($danger ? 'danger' : 'primary')
            ->authorize($ability)
            ->visible(fn (Shipment $record): bool => $record->status !== null && in_array($to, $this->allowedTransitions($record->status), true))
            ->requiresConfirmation()
            ->schema([
                Textarea::make('note')->label('یادداشت عملیاتی')->maxLength(2000),
            ])
            ->action(function (Shipment $record, array $data, ShipmentService $shipments) use ($to): void {
                try {
                    $this->record = $shipments->transition($record, $to, auth()->id(), $data['note'] ?? null);
                    Notification::make()->title('وضعیت مرسوله بروزرسانی شد.')->success()->send();
                } catch (DomainException $exception) {
                    Notification::make()->title('تغییر وضعیت انجام نشد.')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    private function trackingAction(): Action
    {
        return Action::make('update_tracking')
            ->label('ثبت اطلاعات رهگیری')
            ->authorize('updateTracking')
            ->schema([
                TextInput::make('tracking_number')->label('کد رهگیری')->maxLength(100),
                TextInput::make('tracking_url')->label('لینک رهگیری')->url()->maxLength(255),
                Textarea::make('note')->label('یادداشت عملیاتی')->maxLength(2000),
            ])
            ->fillForm(fn (Shipment $record): array => [
                'tracking_number' => $record->tracking_number,
                'tracking_url' => $record->tracking_url,
                'note' => $record->notes,
            ])
            ->action(function (Shipment $record, array $data, ShipmentService $shipments): void {
                try {
                    $this->record = $shipments->updateTracking($record, $data['tracking_number'] ?? null, $data['tracking_url'] ?? null, $data['note'] ?? null);
                    Notification::make()->title('اطلاعات رهگیری ذخیره شد.')->success()->send();
                } catch (DomainException $exception) {
                    Notification::make()->title('ذخیره اطلاعات رهگیری انجام نشد.')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    /** @return array<int, ShipmentStatus> */
    private function allowedTransitions(ShipmentStatus $from): array
    {
        return match ($from) {
            ShipmentStatus::Pending => [ShipmentStatus::Ready, ShipmentStatus::Cancelled],
            ShipmentStatus::Ready => [ShipmentStatus::Shipped, ShipmentStatus::Cancelled],
            ShipmentStatus::Shipped => [ShipmentStatus::Delivered],
            ShipmentStatus::Delivered, ShipmentStatus::Cancelled => [],
        };
    }
}

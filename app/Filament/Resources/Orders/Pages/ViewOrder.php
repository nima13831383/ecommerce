<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\Orders\OrderService;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'جزئیات سفارش';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'user:id,name,email',
            'items.inventoryReservation',
            'payments.transactions',
            'statusHistories.user:id,name',
        ]);
    }

    protected function getHeaderActions(): array
    {
        $orderService = app(OrderService::class);
        $order = $this->getRecord();

        return collect($orderService->allowedTransitionsFor($order))
            ->filter(fn (OrderStatus $status): bool => in_array($status, [
                OrderStatus::AwaitingPayment,
                OrderStatus::Processing,
                OrderStatus::Completed,
                OrderStatus::Cancelled,
                OrderStatus::Failed,
            ], true))
            ->map(fn (OrderStatus $status): Action => $this->makeTransitionAction($status))
            ->all();
    }

    private function makeTransitionAction(OrderStatus $status): Action
    {
        $isCancellation = $status === OrderStatus::Cancelled;
        $label = match ($status) {
            OrderStatus::AwaitingPayment => 'انتقال به انتظار پرداخت',
            OrderStatus::Processing => 'انتقال به در حال پردازش',
            OrderStatus::Shipped => 'ثبت ارسال سفارش',
            OrderStatus::Delivered => 'ثبت تحویل سفارش',
            OrderStatus::Completed => 'تکمیل سفارش',
            OrderStatus::Cancelled => 'لغو سفارش',
            OrderStatus::Failed => 'ثبت سفارش ناموفق',
            OrderStatus::Refunded => 'ثبت مرجوعی',
            default => 'تغییر وضعیت',
        };

        return Action::make("transition_{$status->value}")
            ->label($label)
            ->color($isCancellation ? 'danger' : 'primary')
            ->icon($isCancellation ? 'heroicon-o-x-circle' : 'heroicon-o-arrow-left')
            ->authorize('updateStatus')
            ->requiresConfirmation()
            ->modalHeading($label)
            ->modalSubmitActionLabel('تأیید')
            ->schema([
                Textarea::make('comment')
                    ->label($isCancellation ? 'توضیح لغو' : 'توضیح تغییر وضعیت')
                    ->helperText('این توضیح در تاریخچه سفارش ثبت می‌شود.')
                    ->maxLength(2000),
            ])
            ->action(function (Order $record, array $data, OrderService $orders) use ($status): void {
                try {
                    $this->record = $orders->transitionStatus($record, $status, auth()->id(), $data['comment'] ?? null);

                    Notification::make()
                        ->title('وضعیت سفارش با موفقیت تغییر کرد.')
                        ->success()
                        ->send();
                } catch (DomainException $exception) {
                    Notification::make()
                        ->title('تغییر وضعیت انجام نشد.')
                        ->body('وضعیت سفارش تغییر کرده یا این انتقال در حال حاضر مجاز نیست.')
                        ->danger()
                        ->send();
                }
            });
    }
}

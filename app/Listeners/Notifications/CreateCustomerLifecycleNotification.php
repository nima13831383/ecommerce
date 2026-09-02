<?php

namespace App\Listeners\Notifications;

use App\Enums\CustomerNotificationType;
use App\Events\CustomerLifecycle\OrderCancelled;
use App\Events\CustomerLifecycle\OrderPlaced;
use App\Events\CustomerLifecycle\PaymentSucceeded;
use App\Events\CustomerLifecycle\ShipmentDelivered;
use App\Events\CustomerLifecycle\ShipmentReady;
use App\Events\CustomerLifecycle\ShipmentShipped;
use App\Services\Notifications\CustomerNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class CreateCustomerLifecycleNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(
        OrderPlaced|PaymentSucceeded|OrderCancelled|ShipmentReady|ShipmentShipped|ShipmentDelivered $event,
    ): void {
        $notifications = app(CustomerNotificationService::class);

        try {
            match (true) {
                $event instanceof OrderPlaced => $notifications->forOrder($event->order, CustomerNotificationType::OrderPlaced, [
                    'order_number' => $event->order->order_number,
                    'order_id' => $event->order->id,
                    'amount' => $event->order->grand_total,
                    'created_at' => $event->order->created_at?->toIso8601String(),
                ], "order:{$event->order->id}:placed"),
                $event instanceof PaymentSucceeded => $notifications->forPayment($event->payment),
                $event instanceof OrderCancelled => $notifications->forOrder($event->order, CustomerNotificationType::OrderCancelled, [
                    'order_number' => $event->order->order_number,
                ], "order:{$event->order->id}:cancelled"),
                $event instanceof ShipmentReady => $notifications->forShipment($event->shipment, CustomerNotificationType::ShipmentReady),
                $event instanceof ShipmentShipped => $notifications->forShipment($event->shipment, CustomerNotificationType::ShipmentShipped),
                $event instanceof ShipmentDelivered => $notifications->forShipment($event->shipment, CustomerNotificationType::ShipmentDelivered),
            };
        } catch (Throwable $exception) {
            Log::error('Customer lifecycle notification intent creation failed.', [
                'event' => $event::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

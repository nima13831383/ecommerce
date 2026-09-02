<?php

namespace App\Services\Notifications;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;
use App\Jobs\Notifications\DeliverCustomerNotification;
use App\Models\CustomerNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class CustomerNotificationService
{
    public function __construct(private readonly NotificationChannelResolver $channels) {}

    public function forOrder(Order $order, CustomerNotificationType $type, array $payload, string $key): CustomerNotification
    {
        return $this->createAndQueue($order, $type, $payload, $key);
    }

    public function forPayment(Payment $payment): CustomerNotification
    {
        $order = $payment->order()->firstOrFail();

        return $this->createAndQueue($order, CustomerNotificationType::PaymentSucceeded, [
            'order_number' => $order->order_number,
            'amount' => $payment->paid_amount,
            'currency' => $payment->currency,
        ], "payment:{$payment->id}:succeeded");
    }

    public function forShipment(Shipment $shipment, CustomerNotificationType $type): CustomerNotification
    {
        $order = $shipment->order()->firstOrFail();
        $payload = [
            'order_number' => $order->order_number,
            'shipment_id' => $shipment->id,
            'carrier_service' => $shipment->carrier_service,
        ];

        if ($type === CustomerNotificationType::ShipmentShipped) {
            $payload += [
                'tracking_number' => $shipment->tracking_number,
                'tracking_url' => $shipment->tracking_url,
            ];
        }

        return $this->createAndQueue($order, $type, $payload, "shipment:{$shipment->id}:{$type->value}");
    }

    public function retry(CustomerNotification $notification): CustomerNotification
    {
        $notification = DB::transaction(function () use ($notification): CustomerNotification {
            $notification = CustomerNotification::query()->lockForUpdate()->findOrFail($notification->id);

            if ($notification->status !== CustomerNotificationStatus::Failed) {
                return $notification;
            }

            $notification->forceFill([
                'status' => CustomerNotificationStatus::Pending,
                'last_error' => null,
                'failed_at' => null,
            ])->save();

            return $notification;
        });

        if ($notification->status === CustomerNotificationStatus::Pending) {
            $this->queue($notification);
        }

        return $notification->fresh();
    }

    public function deliver(CustomerNotification $notification): void
    {
        $notification = DB::transaction(function () use ($notification): CustomerNotification {
            $notification = CustomerNotification::query()->lockForUpdate()->findOrFail($notification->id);

            if ($notification->status === CustomerNotificationStatus::Sent) {
                return $notification;
            }

            $notification->forceFill([
                'status' => CustomerNotificationStatus::Queued,
                'attempts' => $notification->attempts + 1,
            ])->save();

            return $notification;
        });

        try {
            $result = $this->channels->resolve()->send($notification);
        } catch (Throwable $exception) {
            $this->markFailed($notification->id, $exception);
            throw $exception;
        }

        if ($result->sent) {
            CustomerNotification::query()->whereKey($notification->id)->update([
                'status' => CustomerNotificationStatus::Sent->value,
                'sent_at' => now(),
            ]);
        }
    }

    public function markFailed(int $notificationId, Throwable $exception): void
    {
        CustomerNotification::query()->whereKey($notificationId)->update([
            'status' => CustomerNotificationStatus::Failed->value,
            'last_error' => Str::limit(trim($exception->getMessage()) ?: 'Notification delivery failed.', 500),
            'failed_at' => now(),
        ]);
    }

    private function createAndQueue(Order $order, CustomerNotificationType $type, array $payload, string $key): CustomerNotification
    {
        try {
            $notification = DB::transaction(function () use ($order, $type, $payload, $key): CustomerNotification {
                return CustomerNotification::query()->firstOrCreate(
                    ['idempotency_key' => $key],
                    [
                        'user_id' => $order->user_id,
                        'order_id' => $order->id,
                        'type' => $type,
                        'channel' => CustomerNotificationChannel::Development,
                        'recipient_snapshot' => $this->recipientSnapshot($order),
                        'payload_snapshot' => $payload,
                        'status' => CustomerNotificationStatus::Pending,
                    ],
                );
            });
        } catch (UniqueConstraintViolationException) {
            $notification = CustomerNotification::query()->where('idempotency_key', $key)->firstOrFail();
        }

        if ($notification->status === CustomerNotificationStatus::Pending) {
            $this->queue($notification);
        }

        return $notification->fresh();
    }

    private function queue(CustomerNotification $notification): void
    {
        $shouldDispatch = DB::transaction(function () use ($notification): bool {
            $notification = CustomerNotification::query()->lockForUpdate()->findOrFail($notification->id);

            if ($notification->status !== CustomerNotificationStatus::Pending) {
                return false;
            }

            $notification->forceFill([
                'status' => CustomerNotificationStatus::Queued,
                'queued_at' => now(),
            ])->save();

            return true;
        });

        if (! $shouldDispatch) {
            return;
        }

        try {
            DeliverCustomerNotification::dispatch($notification->id)->onQueue('notifications')->afterCommit();
        } catch (Throwable $exception) {
            $this->markFailed($notification->id, $exception);
        }
    }

    /** @return array<string, mixed> */
    private function recipientSnapshot(Order $order): array
    {
        return [
            'name' => $order->customer_name,
            'mobile' => $order->customer_mobile,
            'email' => $order->customer_email,
        ];
    }
}

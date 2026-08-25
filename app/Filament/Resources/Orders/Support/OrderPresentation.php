<?php

namespace App\Filament\Resources\Orders\Support;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;

class OrderPresentation
{
    public static function money(mixed $amount): string
    {
        return number_format((int) $amount).' ریال';
    }

    public static function orderStatus(mixed $status): string
    {
        return match (self::value($status)) {
            OrderStatus::Pending->value => 'در انتظار',
            OrderStatus::AwaitingPayment->value => 'در انتظار پرداخت',
            OrderStatus::Processing->value => 'در حال پردازش',
            OrderStatus::Shipped->value => 'ارسال شده',
            OrderStatus::Delivered->value => 'تحویل شده',
            OrderStatus::Completed->value => 'تکمیل شده',
            OrderStatus::Cancelled->value => 'لغو شده',
            OrderStatus::Refunded->value => 'مرجوع شده',
            OrderStatus::Failed->value => 'ناموفق',
            default => (string) self::value($status),
        };
    }

    public static function orderStatusColor(mixed $status): string
    {
        return match (self::value($status)) {
            OrderStatus::Completed->value, OrderStatus::Delivered->value => 'success',
            OrderStatus::Cancelled->value, OrderStatus::Failed->value => 'danger',
            OrderStatus::Processing->value, OrderStatus::Shipped->value => 'info',
            default => 'warning',
        };
    }

    public static function paymentStatus(mixed $status): string
    {
        return match (self::value($status)) {
            OrderPaymentStatus::Unpaid->value => 'پرداخت نشده',
            OrderPaymentStatus::PartiallyPaid->value => 'بخشی پرداخت شده',
            OrderPaymentStatus::Paid->value => 'پرداخت شده',
            OrderPaymentStatus::Refunded->value => 'مرجوع شده',
            OrderPaymentStatus::PartiallyRefunded->value => 'بخشی مرجوع شده',
            PaymentStatus::Pending->value => 'در انتظار',
            PaymentStatus::Processing->value => 'در حال پردازش',
            PaymentStatus::Paid->value => 'پرداخت شده',
            PaymentStatus::Failed->value => 'ناموفق',
            PaymentStatus::Cancelled->value => 'لغو شده',
            PaymentStatus::Expired->value => 'منقضی شده',
            PaymentStatus::Refunded->value => 'مرجوع شده',
            PaymentStatus::PartiallyRefunded->value => 'بخشی مرجوع شده',
            default => (string) self::value($status),
        };
    }

    public static function paymentStatusColor(mixed $status): string
    {
        return match (self::value($status)) {
            OrderPaymentStatus::Paid->value, PaymentStatus::Paid->value => 'success',
            OrderPaymentStatus::Refunded->value, OrderPaymentStatus::PartiallyRefunded->value,
            PaymentStatus::Refunded->value, PaymentStatus::PartiallyRefunded->value => 'info',
            OrderPaymentStatus::Unpaid->value, PaymentStatus::Failed->value,
            PaymentStatus::Cancelled->value, PaymentStatus::Expired->value => 'danger',
            default => 'warning',
        };
    }

    public static function reservationStatus(mixed $status): string
    {
        return match (self::value($status)) {
            InventoryReservationStatus::Active->value => 'فعال',
            InventoryReservationStatus::Committed->value => 'قطعی شده',
            InventoryReservationStatus::Released->value => 'آزاد شده',
            InventoryReservationStatus::Expired->value => 'منقضی شده',
            default => (string) self::value($status),
        };
    }

    public static function reservationStatusColor(mixed $status): string
    {
        return match (self::value($status)) {
            InventoryReservationStatus::Active->value => 'warning',
            InventoryReservationStatus::Committed->value => 'success',
            InventoryReservationStatus::Released->value, InventoryReservationStatus::Expired->value => 'gray',
            default => 'gray',
        };
    }

    /** @return array{label: string, color: string} */
    public static function reservationCoverage(Order $order): array
    {
        $statuses = $order->items
            ->map(fn ($item) => $item->inventoryReservation?->status)
            ->filter();

        if ($statuses->contains(fn ($status) => self::value($status) === InventoryReservationStatus::Committed->value)) {
            return ['label' => 'موجودی قطعی شده', 'color' => 'success'];
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => self::value($status) === InventoryReservationStatus::Active->value)) {
            return ['label' => 'پوشش رزرو فعال', 'color' => 'warning'];
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => in_array(self::value($status), [InventoryReservationStatus::Released->value, InventoryReservationStatus::Expired->value], true))) {
            return ['label' => 'رزرو آزاد یا منقضی شده', 'color' => 'gray'];
        }

        return ['label' => 'پوشش رزرو ناقص', 'color' => 'danger'];
    }

    public static function hasReservationWarning(Order $order): bool
    {
        if ($order->status !== OrderStatus::Pending || $order->payment_status !== OrderPaymentStatus::Unpaid) {
            return false;
        }

        return $order->items->contains(function ($item): bool {
            $reservation = $item->inventoryReservation;

            return $reservation === null || in_array(self::value($reservation->status), [InventoryReservationStatus::Expired->value, InventoryReservationStatus::Released->value], true);
        });
    }

    public static function value(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    public static function json(mixed $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '—';
    }
}

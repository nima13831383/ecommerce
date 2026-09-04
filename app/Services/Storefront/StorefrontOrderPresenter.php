<?php

namespace App\Services\Storefront;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Http\Resources\Api\V1\PublicImageResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Shipment;
use Illuminate\Support\Arr;

class StorefrontOrderPresenter
{
    /** @return array<string, mixed> */
    public function summary(Order $order): array
    {
        $item = $order->items->first();

        return [
            'order_number' => $order->order_number,
            'created_at' => $order->created_at,
            'status' => $this->orderStatus($order->status),
            'payment_status' => $this->paymentStatus($order->payment_status),
            'grand_total' => (int) $order->grand_total,
            'item_count' => (int) ($order->items_count ?? $order->items->sum('quantity')),
            'preview' => $item ? $this->item($item) : null,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Order $order): array
    {
        $payment = $order->payments->first();

        return [
            'order_number' => $order->order_number,
            'created_at' => $order->created_at,
            'customer_note' => $order->customer_note,
            'status' => $this->orderStatus($order->status),
            'payment_status' => $this->paymentStatus($order->payment_status),
            'items' => $order->items->map(fn (OrderItem $item): array => $this->item($item))->values()->all(),
            'totals' => [
                'items_subtotal' => (int) $order->items_subtotal,
                'discount_total' => (int) $order->discount_total,
                'tax_total' => (int) $order->tax_total,
                'shipping_total' => (int) $order->shipping_total,
                'grand_total' => (int) $order->grand_total,
            ],
            'coupon' => $this->coupon($order->coupon_snapshot),
            'shipping' => $this->shipping($order),
            'shipping_address' => $this->address($order->shipping_address),
            'billing_address' => $this->address($order->billing_address),
            'payment' => $this->payment($payment),
            'timeline' => $order->statusHistories
                ->where('type', 'status')
                ->map(fn ($history): ?array => $this->timelineEntry($history->to_status, $history->created_at))
                ->filter()
                ->values()
                ->all(),
            'shipment' => $this->shipment($order->shipment),
        ];
    }

    public function paymentRetryAllowed(Order $order, bool $gatewayAvailable): bool
    {
        if (! $gatewayAvailable || $order->payment_status !== OrderPaymentStatus::Unpaid) {
            return false;
        }

        if (! in_array($order->status, [OrderStatus::Pending, OrderStatus::AwaitingPayment, OrderStatus::Failed], true)) {
            return false;
        }

        return $order->items->isNotEmpty()
            && $order->items->every(fn (OrderItem $item): bool => $item->inventoryReservation?->status?->value === 'active'
                && $item->inventoryReservation->expires_at?->isFuture());
    }

    /** @return array<string, mixed> */
    private function item(OrderItem $item): array
    {
        $product = $item->product;
        $image = $product?->primaryImage;

        return [
            'name' => $item->product_name,
            'sku' => $item->sku,
            'variation_attributes' => is_array($item->variation_attributes) ? $item->variation_attributes : [],
            'quantity' => (int) $item->quantity,
            'unit_price' => (int) $item->unit_price,
            'discount_amount' => (int) $item->discount_amount,
            'tax_amount' => (int) $item->tax_amount,
            'line_total' => (int) $item->line_total,
            'image' => $image ? (new PublicImageResource($image))->toArray(request()) : null,
            'url' => $product && ! $product->trashed() && $product->status === 'published'
                ? route('storefront.products.show', ['product' => $product->slug])
                : null,
        ];
    }

    /** @return array<string, string> */
    private function orderStatus(OrderStatus $status): array
    {
        return ['value' => $status->value, 'label' => [
            'pending' => 'در انتظار بررسی',
            'awaiting_payment' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'shipped' => 'ارسال شده',
            'delivered' => 'تحویل شده',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازپرداخت شده',
            'failed' => 'ناموفق',
        ][$status->value] ?? $status->value];
    }

    /** @return array<string, string> */
    private function paymentStatus(OrderPaymentStatus $status): array
    {
        return ['value' => $status->value, 'label' => [
            'unpaid' => 'پرداخت نشده',
            'partially_paid' => 'پرداخت ناقص',
            'paid' => 'پرداخت شده',
            'refunded' => 'بازپرداخت شده',
            'partially_refunded' => 'بازپرداخت ناقص',
        ][$status->value] ?? $status->value];
    }

    /** @return array<string, mixed>|null */
    private function timelineEntry(string $status, mixed $createdAt): ?array
    {
        $resolved = OrderStatus::tryFrom($status);
        if (! $resolved) {
            return null;
        }

        $presentation = $this->orderStatus($resolved);

        return ['value' => $presentation['value'], 'label' => $presentation['label'], 'created_at' => $createdAt];
    }

    /** @return array<string, mixed>|null */
    private function payment(?Payment $payment): ?array
    {
        if (! $payment) {
            return null;
        }

        return [
            'status' => $this->paymentAttemptStatus($payment->status->value),
            'amount' => (int) $payment->amount,
            'created_at' => $payment->created_at,
            'paid_at' => $payment->paid_at,
        ];
    }

    /** @return array<string, string> */
    private function paymentAttemptStatus(string $status): array
    {
        return ['value' => $status, 'label' => [
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'paid' => 'پرداخت شده',
            'failed' => 'ناموفق',
            'cancelled' => 'لغو شده',
            'expired' => 'منقضی شده',
            'refunded' => 'بازپرداخت شده',
            'partially_refunded' => 'بازپرداخت ناقص',
        ][$status] ?? $status];
    }

    /** @return array<string, mixed>|null */
    private function shipment(?Shipment $shipment): ?array
    {
        if (! $shipment) {
            return null;
        }

        return [
            'number' => $shipment->shipment_number,
            'status' => $this->shipmentStatus($shipment->status),
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'carrier' => $shipment->carrier_service ?: $shipment->carrier,
            'shipped_at' => $shipment->shipped_at,
            'delivered_at' => $shipment->delivered_at,
            'timeline' => $shipment->statusHistories->map(function ($history): ?array {
                $status = ShipmentStatus::tryFrom($history->to_status);
                if (! $status) {
                    return null;
                }

                return [
                    'value' => $status->value,
                    'label' => $this->shipmentStatus($status)['label'],
                    'created_at' => $history->created_at,
                ];
            })->filter()->values()->all(),
        ];
    }

    /** @return array<string, string> */
    private function shipmentStatus(ShipmentStatus $status): array
    {
        return ['value' => $status->value, 'label' => [
            'pending' => 'در انتظار پردازش',
            'ready' => 'آماده ارسال',
            'shipped' => 'ارسال شده',
            'delivered' => 'تحویل شده',
            'cancelled' => 'لغو شده',
        ][$status->value] ?? $status->value];
    }

    /** @return array<string, mixed>|null */
    private function shipping(Order $order): ?array
    {
        $snapshot = is_array($order->shipping_snapshot) ? $order->shipping_snapshot : [];
        if ($snapshot === [] && $order->shipping_total === 0) {
            return null;
        }

        return [
            'service' => $snapshot['service'] ?? $snapshot['method_name'] ?? null,
            'mode' => $snapshot['mode'] ?? null,
            'payment_type' => $snapshot['payment_type'] ?? null,
            'amount' => (int) $order->shipping_total,
            'currency' => $order->currency,
        ];
    }

    /** @return array<string, mixed>|null */
    private function coupon(?array $snapshot): ?array
    {
        if (! is_array($snapshot) || ! isset($snapshot['code'])) {
            return null;
        }

        return [
            'code' => (string) $snapshot['code'],
            'type' => isset($snapshot['type']) ? (string) $snapshot['type'] : null,
            'discount_amount' => (int) ($snapshot['discount_amount'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|null */
    private function address(?array $snapshot): ?array
    {
        if (! is_array($snapshot)) {
            return null;
        }

        return Arr::only($snapshot, [
            'first_name', 'last_name', 'mobile', 'company', 'province_name', 'city_name',
            'postal_code', 'address_line', 'plaque', 'unit',
        ]);
    }
}

<?php

namespace App\Services\Fulfillment;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Events\CustomerLifecycle\ShipmentDelivered;
use App\Events\CustomerLifecycle\ShipmentReady;
use App\Events\CustomerLifecycle\ShipmentShipped;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Orders\OrderService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShipmentService
{
    public function __construct(private readonly OrderService $orders) {}

    public function ensure(Order $order, ?int $actorId = null, ?string $note = null): Shipment
    {
        return DB::transaction(function () use ($order, $actorId, $note): Shipment {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            $existing = $order->shipment()->first();

            if ($existing) {
                return $existing;
            }

            if ($order->status === OrderStatus::Cancelled) {
                throw new DomainException('A shipment cannot be started for a cancelled order.');
            }

            $snapshot = $order->shipping_snapshot ?? [];
            $shipment = $order->shipment()->create([
                'shipment_number' => 'SHP-'.Str::upper((string) Str::ulid()),
                'status' => ShipmentStatus::Pending,
                'method_name' => $snapshot['service'] ?? null,
                'carrier' => $snapshot['service'] ?? null,
                'carrier_service' => $snapshot['service'] ?? null,
                'tracking_number' => null,
                'shipping_cost' => $order->shipping_total,
                'weight' => $snapshot['total_weight'] ?? $snapshot['weight'] ?? null,
                'shipping_address' => $order->shipping_address,
                'shipping_snapshot' => $snapshot,
                'notes' => $note,
            ]);

            $shipment->statusHistories()->create([
                'to_status' => ShipmentStatus::Pending->value,
                'user_id' => $actorId,
                'note' => $note,
            ]);

            return $shipment->load('statusHistories');
        });
    }

    public function transition(Shipment $shipment, ShipmentStatus|string $to, ?int $actorId = null, ?string $note = null): Shipment
    {
        $to = $to instanceof ShipmentStatus ? $to : ShipmentStatus::from($to);

        $shipment = DB::transaction(function () use ($shipment, $to, $actorId, $note): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $from = $shipment->status;

            if ($from === $to) {
                return $shipment->load('statusHistories');
            }

            if (! in_array($to, $this->allowedTransitions($from), true)) {
                throw new DomainException("The shipment cannot transition from {$from->value} to {$to->value}.");
            }

            $order = Order::query()->lockForUpdate()->findOrFail($shipment->order_id);

            if ($to === ShipmentStatus::Shipped) {
                $this->assertReadyForShipment($order);
            }

            if ($to === ShipmentStatus::Delivered && $order->status === OrderStatus::Shipped) {
                $this->orders->transitionStatus($order, OrderStatus::Delivered, $actorId, $note);
            }

            $attributes = ['status' => $to];

            if ($to === ShipmentStatus::Shipped && $shipment->shipped_at === null) {
                $attributes['shipped_at'] = now();
            }

            if ($to === ShipmentStatus::Delivered && $shipment->delivered_at === null) {
                $attributes['delivered_at'] = now();
            }

            if ($to === ShipmentStatus::Cancelled && $shipment->cancelled_at === null) {
                $attributes['cancelled_at'] = now();
            }

            if ($note !== null) {
                $attributes['notes'] = $note;
            }

            $shipment->forceFill($attributes)->save();
            $shipment->statusHistories()->create([
                'from_status' => $from->value,
                'to_status' => $to->value,
                'user_id' => $actorId,
                'note' => $note,
            ]);

            if ($to === ShipmentStatus::Shipped && $order->status === OrderStatus::Processing) {
                $this->orders->transitionStatus($order, OrderStatus::Shipped, $actorId, $note);
            }

            return $shipment->load('statusHistories');
        });

        match ($to) {
            ShipmentStatus::Ready => event(new ShipmentReady($shipment)),
            ShipmentStatus::Shipped => event(new ShipmentShipped($shipment)),
            ShipmentStatus::Delivered => event(new ShipmentDelivered($shipment)),
            default => null,
        };

        return $shipment;
    }

    public function updateTracking(Shipment $shipment, ?string $trackingNumber, ?string $trackingUrl = null, ?string $note = null): Shipment
    {
        $trackingNumber = filled($trackingNumber) ? trim($trackingNumber) : null;
        $trackingUrl = filled($trackingUrl) ? trim($trackingUrl) : null;

        if ($trackingNumber !== null && mb_strlen($trackingNumber) > 100) {
            throw new DomainException('The tracking number is too long.');
        }

        if ($trackingUrl !== null && (! filter_var($trackingUrl, FILTER_VALIDATE_URL) || mb_strlen($trackingUrl) > 255)) {
            throw new DomainException('The tracking URL is invalid.');
        }

        return DB::transaction(function () use ($shipment, $trackingNumber, $trackingUrl, $note): Shipment {
            $shipment = Shipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $shipment->forceFill([
                'tracking_number' => $trackingNumber,
                'tracking_url' => $trackingUrl,
                ...($note !== null ? ['notes' => $note] : []),
            ])->save();

            return $shipment->fresh(['order', 'statusHistories']);
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

    private function assertReadyForShipment(Order $order): void
    {
        if ($order->status === OrderStatus::Cancelled) {
            throw new DomainException('A cancelled order cannot be shipped.');
        }

        if ($order->payment_status !== OrderPaymentStatus::Paid) {
            throw new DomainException('The order must have a successful payment before shipment.');
        }

        $items = $order->items()->with('inventoryReservation')->get();

        if ($items->isEmpty() || $items->contains(fn ($item): bool => $item->inventoryReservation?->status !== InventoryReservationStatus::Committed)) {
            throw new DomainException('Inventory must be committed before shipment.');
        }

        if (! in_array($order->status, [OrderStatus::Processing, OrderStatus::Shipped], true)) {
            throw new DomainException('The order is not ready for shipment.');
        }
    }
}

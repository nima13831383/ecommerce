<?php

namespace App\Observers;

use App\Models\Shipment;
use App\Models\Order;
// app/Observers/ShipmentObserver.php
class ShipmentObserver
{
    public function saved(Shipment $shipment): void
    {
        if (! $shipment->wasChanged('status') && ! $shipment->wasRecentlyCreated) {
            return;
        }
        $this->syncOrderStatus($shipment->order);
    }

    // اگر همه مرسولات تحویل شد → سفارش completed؛ اگر بخشی ارسال شد → partially_shipped
    protected function syncOrderStatus(?Order $order): void
    {
        if (! $order) {
            return;
        }

        $shipments = $order->shipments()->get();
        if ($shipments->isEmpty()) {
            return;
        }

        $all = $shipments->count();
        $delivered = $shipments->where('status', 'delivered')->count();
        $shipped   = $shipments->whereIn('status', ['shipped', 'delivered'])->count();

        $new = match (true) {
            $delivered === $all => 'completed',
            $shipped > 0        => 'partially_shipped',
            default             => $order->status,
        };

        if ($new !== $order->status) {
            $order->update(['status' => $new]);
        }
    }
}

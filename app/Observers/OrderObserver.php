<?php

namespace App\Observers;

use App\Models\Order;
use App\Models\OrderStatusHistory;
// app/Observers/OrderObserver.php
class OrderObserver
{
    // ثبت هر تغییر وضعیت در تاریخچه ممیزی
    public function updated(Order $order): void
    {
        if (! $order->wasChanged('status')) {
            return;
        }

        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status' => $order->getOriginal('status'),
            'to_status'  => $order->status,
            'note'       => null,
            'changed_by' => auth()->id(),
        ]);
    }

    public function created(Order $order): void
    {
        OrderStatusHistory::create([
            'order_id'   => $order->id,
            'from_status' => null,
            'to_status'  => $order->status,
            'note'       => 'ثبت اولیه سفارش',
            'changed_by' => auth()->id(),
        ]);
    }
}

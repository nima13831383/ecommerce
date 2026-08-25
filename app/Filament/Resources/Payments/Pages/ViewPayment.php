<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected static ?string $title = 'جزئیات پرداخت';

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load([
            'order:id,order_number,customer_name,customer_mobile,customer_email,status,payment_status,grand_total,currency',
            'order.items.inventoryReservation',
            'order.payments:id,order_id,payment_number,status,amount,paid_amount,currency,gateway,authority,reference_id,reconciliation_required,verified_at,created_at',
            'transactions:id,payment_id,type,status,amount,authority,reference_id,gateway_status_code,request_payload,response_payload,message,created_at',
        ]);
    }
}

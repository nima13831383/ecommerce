<?php

namespace App\Enums;

enum CustomerNotificationType: string
{
    case OrderPlaced = 'order_placed';
    case PaymentSucceeded = 'payment_succeeded';
    case OrderCancelled = 'order_cancelled';
    case ShipmentReady = 'shipment_ready';
    case ShipmentShipped = 'shipment_shipped';
    case ShipmentDelivered = 'shipment_delivered';
}

<?php

namespace App\Enums;

enum ShippingMode: string
{
    case Calculator = 'calculator';
    case Fixed = 'fixed';
    case Free = 'free';
}

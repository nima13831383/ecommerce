<?php

namespace App\Enums;

enum CustomerNotificationChannel: string
{
    case Development = 'development';
    case Sms = 'sms';
    case Email = 'email';
}

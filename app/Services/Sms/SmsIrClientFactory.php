<?php

namespace App\Services\Sms;

use Ipe\Sdk\SmsIrService;

class SmsIrClientFactory
{
    public function make(string $apiKey): SmsIrService
    {
        return new SmsIrService($apiKey, SmsGatewayConfiguration::BASE_URI);
    }
}

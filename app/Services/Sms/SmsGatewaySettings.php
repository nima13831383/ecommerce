<?php

namespace App\Services\Sms;

final readonly class SmsGatewaySettings
{
    public function __construct(
        public string $apiKey,
        public bool $sandbox,
        public int $templateId,
        public string $parameterName,
    ) {}
}

<?php

namespace App\Services\Sms;

use App\Services\Settings\SettingsService;

class SmsGatewayConfiguration
{
    public const SANDBOX_TEMPLATE_ID = 123456;

    public const SANDBOX_PARAMETER_NAME = 'CODE';

    public const BASE_URI = 'https://api.sms.ir/v1/';

    public function __construct(private readonly SettingsService $settings) {}

    public function settings(): ?SmsGatewaySettings
    {
        if ($this->settings->get('sms.default_provider') !== 'smsir' || $this->settings->get('sms.smsir.enabled') !== true) {
            return null;
        }

        $sandbox = $this->settings->get('sms.smsir.sandbox') === true;
        $apiKey = $this->settings->get('sms.smsir.api_key');

        if (! is_string($apiKey) || blank($apiKey) || ($sandbox && app()->isProduction())) {
            return null;
        }

        $templateId = $sandbox
            ? self::SANDBOX_TEMPLATE_ID
            : $this->settings->get('sms.smsir.verify_template_id');
        $parameterName = $sandbox
            ? self::SANDBOX_PARAMETER_NAME
            : $this->settings->get('sms.smsir.verify_parameter_name');

        if (! is_int($templateId) || $templateId < 1 || ! is_string($parameterName) || ! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,31}$/', $parameterName)) {
            return null;
        }

        return new SmsGatewaySettings(trim($apiKey), $sandbox, $templateId, $parameterName);
    }

    public function operational(): bool
    {
        return $this->settings() !== null;
    }
}

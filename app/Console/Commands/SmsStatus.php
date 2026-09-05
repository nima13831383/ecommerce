<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use App\Services\Sms\SmsGatewayConfiguration;
use Illuminate\Console\Command;

class SmsStatus extends Command
{
    protected $signature = 'sms:status';

    protected $description = 'Report safe SMS.ir runtime diagnostics without sending a message or exposing credentials.';

    public function handle(SettingsService $settings, SmsGatewayConfiguration $configuration): int
    {
        $sandbox = $settings->get('sms.smsir.sandbox') === true;
        $this->line('Provider: '.($settings->get('sms.default_provider') ?? 'not configured'));
        $this->line('Enabled: '.($settings->get('sms.smsir.enabled') === true ? 'yes' : 'no'));
        $this->line('Sandbox: '.($sandbox ? 'yes' : 'no'));
        $this->line('API key configured: '.(filled($settings->get('sms.smsir.api_key')) ? 'yes' : 'no'));
        $this->line('Effective template ID: '.($sandbox ? SmsGatewayConfiguration::SANDBOX_TEMPLATE_ID : ($settings->get('sms.smsir.verify_template_id') ?? 'not configured')));
        $this->line('Effective parameter: '.($sandbox ? SmsGatewayConfiguration::SANDBOX_PARAMETER_NAME : $settings->get('sms.smsir.verify_parameter_name')));
        $this->line('Customer auth mode: '.$settings->get('auth.customer_auth_mode'));
        $this->line('Environment: '.app()->environment());
        $this->line('Operational: '.($configuration->operational() ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}

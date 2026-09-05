<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\Payments\PaymentGatewayConfiguration;
use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class ImportZarinPalEnvironment extends Command
{
    protected $signature = 'payment:import-zarinpal-env {--dry-run : Report the legacy configuration without writing settings} {--force : Replace an already configured merchant ID}';

    protected $description = 'Import intentional legacy ZarinPal environment configuration into encrypted core Site Settings.';

    public function handle(SettingsService $settings): int
    {
        $gateway = config('payment.legacy.default_gateway');
        $merchantId = config('payment.legacy.zarinpal.merchant_id');
        $sandbox = config('payment.legacy.zarinpal.sandbox') === true;
        $configured = $gateway === 'zarinpal' && PaymentGatewayConfiguration::validMerchantId($merchantId);

        $this->line('Legacy default gateway: '.($gateway === 'zarinpal' ? 'zarinpal' : 'not configured'));
        $this->line('Legacy merchant ID: '.(is_string($merchantId) && $merchantId !== '' ? 'present' : 'not configured'));
        $this->line('Legacy sandbox: '.($sandbox ? 'enabled' : 'disabled'));

        if (! $configured) {
            $this->warn('No valid intentional legacy ZarinPal configuration was found. No settings were written.');

            return self::SUCCESS;
        }

        $existingMerchant = $settings->get('payment.zarinpal.merchant_id');
        $replaceMerchant = $existingMerchant === null || (bool) $this->option('force');

        if ($this->option('dry-run')) {
            $this->info('Dry run: no settings were written.');
            $this->line('Merchant import: '.($replaceMerchant ? 'would import encrypted credential' : 'would preserve existing credential'));

            return self::SUCCESS;
        }

        if ($replaceMerchant) {
            $settings->update('payment.zarinpal.merchant_id', $merchantId);
        }

        $settings->update('payment.zarinpal.sandbox', $sandbox);
        $settings->update('payment.default_gateway', 'zarinpal');
        $settings->update('payment.zarinpal.enabled', true);

        $stored = Setting::query()
            ->where('group', 'payment')
            ->where('key', 'payment.zarinpal.merchant_id')
            ->value('value');

        if (! is_string($stored) || $stored === (string) $merchantId) {
            $this->error('The merchant credential was not stored securely.');

            return self::FAILURE;
        }

        $this->info('ZarinPal Site Settings imported. Merchant credential is encrypted and was not displayed.');

        return self::SUCCESS;
    }
}

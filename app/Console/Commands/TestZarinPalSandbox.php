<?php

namespace App\Console\Commands;

use App\Services\Payments\ZarinPalSdkClient;
use Illuminate\Console\Command;
use Throwable;

class TestZarinPalSandbox extends Command
{
    protected $signature = 'payment:test-zarinpal-sandbox {--amount=10000 : Amount in integer IRR}';

    protected $description = 'Run a local-only ZarinPal sandbox payment request (external integration check).';

    public function handle(): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('This external sandbox check is allowed only in local, development, or testing environments.');

            return self::FAILURE;
        }

        try {
            $amount = (int) $this->option('amount');
            $client = new ZarinPalSdkClient;
            $response = $client->request(
                $amount,
                'Sandbox payment integration check',
                route('storefront.payment.return', ['payment' => 0]),
                'IRR',
            );

            if ((int) ($response['code'] ?? 0) !== 100 || ! is_string($response['authority'] ?? null)) {
                $this->error('ZarinPal sandbox request was rejected.');

                return self::FAILURE;
            }

            $this->info('Authority: '.$response['authority']);
            $this->line('Redirect: '.$client->redirectUrl($response['authority']));
        } catch (Throwable $exception) {
            $this->error('ZarinPal sandbox request failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}

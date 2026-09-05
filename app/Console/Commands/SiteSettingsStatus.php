<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class SiteSettingsStatus extends Command
{
    protected $signature = 'settings:status';

    protected $description = 'Report registered, persisted, unknown, and incomplete site settings.';

    public function handle(SettingsService $settings): int
    {
        $status = $settings->status();

        $this->line("Registered: {$status['registered']}");
        $this->line("Persisted: {$status['persisted']}");
        $this->line('Missing: '.($status['missing'] === [] ? 'none' : implode(', ', $status['missing'])));
        $unknown = collect($status['unknown'])
            ->map(fn (array $setting): string => "{$setting['group']}.{$setting['key']}")
            ->implode(', ');
        $this->line('Unknown: '.($unknown === '' ? 'none' : $unknown));
        $this->line('Needs configuration: '.($status['needs_configuration'] === [] ? 'none' : implode(', ', $status['needs_configuration'])));
        $this->line('Payment default gateway: '.$status['payment']['default_gateway']);
        $this->line('Payment ZarinPal enabled: '.($status['payment']['enabled'] ? 'yes' : 'no'));
        $this->line('Payment ZarinPal sandbox: '.($status['payment']['sandbox'] ? 'yes' : 'no'));
        $this->line('Payment ZarinPal merchant: '.($status['payment']['merchant_configured'] ? 'configured' : 'not configured'));
        $this->line('Payment ZarinPal operational: '.($status['payment']['operational'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}

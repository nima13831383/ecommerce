<?php

namespace App\Console\Commands;

use App\Services\Settings\SettingsService;
use Illuminate\Console\Command;

class SyncSiteSettings extends Command
{
    protected $signature = 'settings:sync {--dry-run : Report missing core rows without writing them}';

    protected $description = 'Persist missing registered core site settings without overwriting values.';

    public function handle(SettingsService $settings): int
    {
        $result = $settings->sync((bool) $this->option('dry-run'));

        $this->info($this->option('dry-run') ? 'Dry run: no settings were written.' : 'Core settings synchronized.');
        $this->line('Added: '.($result['added'] === [] ? 'none' : implode(', ', $result['added'])));
        $this->line('Existing: '.($result['existing'] === [] ? 'none' : implode(', ', $result['existing'])));

        return self::SUCCESS;
    }
}

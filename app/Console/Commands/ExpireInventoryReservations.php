<?php

namespace App\Console\Commands;

use App\Services\Inventory\InventoryService;
use Illuminate\Console\Command;

class ExpireInventoryReservations extends Command
{
    protected $signature = 'inventory:expire-reservations';

    protected $description = 'Expire due active inventory reservations.';

    public function handle(InventoryService $inventory): int
    {
        $count = $inventory->expireDueReservations();
        $this->info("{$count} inventory reservation(s) expired.");

        return self::SUCCESS;
    }
}

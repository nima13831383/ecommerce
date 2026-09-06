<?php

namespace App\Console\Commands;

use App\Enums\CacheRebuildDomain;
use App\Services\Storefront\StorefrontCacheRebuildService;
use Illuminate\Console\Command;

class RebuildStorefrontProductsCache extends Command
{
    protected $signature = 'cache:rebuild-products';

    protected $description = 'Queue an asynchronous storefront Product cache rebuild.';

    public function handle(StorefrontCacheRebuildService $rebuilds): int
    {
        $run = $rebuilds->request(CacheRebuildDomain::Products);
        $this->info("Product cache rebuild run #{$run->id} is {$run->status->value}.");

        return self::SUCCESS;
    }
}

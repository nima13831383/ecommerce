<?php

namespace App\Console\Commands;

use App\Enums\CacheRebuildDomain;
use App\Services\Storefront\StorefrontCacheRebuildService;
use Illuminate\Console\Command;

class RebuildStorefrontBlogCache extends Command
{
    protected $signature = 'cache:rebuild-blog';

    protected $description = 'Queue an asynchronous storefront Blog cache rebuild.';

    public function handle(StorefrontCacheRebuildService $rebuilds): int
    {
        $run = $rebuilds->request(CacheRebuildDomain::Blog);
        $this->info("Blog cache rebuild run #{$run->id} is {$run->status->value}.");

        return self::SUCCESS;
    }
}

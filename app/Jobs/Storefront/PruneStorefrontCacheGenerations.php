<?php

namespace App\Jobs\Storefront;

use App\Services\Storefront\StorefrontCacheRebuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneStorefrontCacheGenerations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(public readonly string $backend) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['domain:cache', 'operation:prune', "cache-backend:{$this->backend}"];
    }

    public function handle(StorefrontCacheRebuildService $rebuilds): void
    {
        $rebuilds->prune($this->backend);
    }
}

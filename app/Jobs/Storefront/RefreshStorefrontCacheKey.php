<?php

namespace App\Jobs\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Services\Storefront\StorefrontCacheRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RefreshStorefrontCacheKey implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public CacheRebuildDomain $domain, public string $scope, public array $parameters, public int $generation, public string $backend) {}

    public function handle(StorefrontCacheRefreshService $service): void
    {
        $service->refresh($this->domain, $this->scope, $this->parameters, $this->generation, $this->backend);
    }

    public function tags(): array
    {
        return ['storefront-cache', 'refresh-ahead', 'domain:'.$this->domain->value];
    }
}

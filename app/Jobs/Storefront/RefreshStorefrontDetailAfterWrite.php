<?php

namespace App\Jobs\Storefront;

use App\Enums\CacheRebuildDomain;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RefreshStorefrontDetailAfterWrite implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 120;

    public array $backoff = [5, 30];

    public function __construct(public CacheRebuildDomain $domain, public int $entityId) {}

    public function handle(StorefrontDetailCacheRefreshService $service): void
    {
        match ($this->domain) {
            CacheRebuildDomain::Products => $service->refreshProduct($this->entityId),
            CacheRebuildDomain::Blog => $service->refreshPost($this->entityId),
        };
    }

    public function uniqueId(): string
    {
        return "{$this->domain->value}-detail:{$this->entityId}";
    }

    public function tags(): array
    {
        $entity = $this->domain === CacheRebuildDomain::Products ? 'product' : 'post';

        return ['domain:cache', "cache:{$entity}-detail", "{$entity}:{$this->entityId}", 'operation:refresh-after-write'];
    }

    public function failed(?Throwable $exception): void
    {
        report($exception);
    }
}

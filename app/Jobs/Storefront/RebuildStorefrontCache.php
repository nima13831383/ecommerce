<?php

namespace App\Jobs\Storefront;

use App\Enums\CacheRebuildStatus;
use App\Models\CacheRebuildRun;
use App\Services\Storefront\StorefrontCacheRebuildService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RebuildStorefrontCache implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public readonly int $runId) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        $run = CacheRebuildRun::query()->find($this->runId);
        $domain = $run?->domain?->value ?? 'unknown';

        return ['domain:cache', "cache:{$domain}", 'operation:rebuild', "cache-run:{$this->runId}"];
    }

    public function handle(StorefrontCacheRebuildService $rebuilds): void
    {
        $rebuilds->rebuild(CacheRebuildRun::query()->findOrFail($this->runId));
    }

    public function failed(Throwable $exception): void
    {
        CacheRebuildRun::query()->whereKey($this->runId)->update([
            'status' => CacheRebuildStatus::Failed,
            'finished_at' => now(),
            'error_summary' => str($exception->getMessage())->limit(1000),
        ]);
    }
}

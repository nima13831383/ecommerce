<?php

namespace App\Services\Storefront;

use App\Services\Settings\SettingsService;
use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

class StorefrontCacheStoreResolver
{
    public function __construct(private readonly SettingsService $settings) {}

    public function name(): string
    {
        return $this->settings->get('cache.store') === 'redis' ? 'redis' : 'database';
    }

    public function store(?string $name = null): Repository
    {
        return Cache::store($name ?? $this->name());
    }

    public function redisReady(): bool
    {
        if (! array_key_exists('redis', config('cache.stores', []))) {
            return false;
        }

        try {
            Cache::store('redis')->get('storefront:readiness:probe');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function selectedStoreReady(): bool
    {
        if ($this->name() !== 'redis') {
            return true;
        }

        return $this->redisReady();
    }
}

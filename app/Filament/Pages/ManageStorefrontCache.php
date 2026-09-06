<?php

namespace App\Filament\Pages;

use App\Enums\CacheRebuildDomain;
use App\Models\CacheRebuildRun;
use App\Models\StorefrontCacheGenerationPrune;
use App\Services\Settings\SettingsService;
use App\Services\Storefront\StorefrontCacheObservability;
use App\Services\Storefront\StorefrontCacheRebuildService;
use App\Services\Storefront\StorefrontCacheStoreResolver;
use App\Services\Storefront\StorefrontQueryCache;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Laravel\Horizon\Horizon;
use UnitEnum;

class ManageStorefrontCache extends Page
{
    protected static ?string $navigationLabel = 'مدیریت کش و صف‌ها';

    protected static ?string $title = 'مدیریت کش و صف‌ها';

    protected static string|UnitEnum|null $navigationGroup = 'site-settings';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;

    protected string $view = 'filament.pages.manage-storefront-cache';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('settings.update') ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rebuildProducts')
                ->label('پاکسازی و بازسازی کش محصولات')
                ->requiresConfirmation()
                ->action(fn () => $this->requestRebuild(CacheRebuildDomain::Products)),
            Action::make('rebuildBlog')
                ->label('پاکسازی و بازسازی کش مقالات')
                ->requiresConfirmation()
                ->action(fn () => $this->requestRebuild(CacheRebuildDomain::Blog)),
        ];
    }

    public function getViewData(): array
    {
        $resolver = app(StorefrontCacheStoreResolver::class);
        $cache = app(StorefrontQueryCache::class);
        $rebuilds = app(StorefrontCacheRebuildService::class);

        return [
            'store' => $resolver->name(),
            'redisReady' => $resolver->redisReady(),
            'horizonAvailable' => class_exists(Horizon::class) && config('queue.default') === 'redis',
            'queueConnection' => config('queue.default'),
            'productGeneration' => $cache->activeGeneration(CacheRebuildDomain::Products),
            'blogGeneration' => $cache->activeGeneration(CacheRebuildDomain::Blog),
            'productRetainedGenerations' => $rebuilds->retainedGenerations(CacheRebuildDomain::Products),
            'blogRetainedGenerations' => $rebuilds->retainedGenerations(CacheRebuildDomain::Blog),
            'lastPrune' => StorefrontCacheGenerationPrune::query()->latest('id')->first(),
            'runs' => CacheRebuildRun::query()->latest('id')->limit(10)->get(),
            'refreshAheadEnabled' => app(SettingsService::class)->get('cache.refresh_ahead.enabled'),
            'refreshAheadPercent' => app(SettingsService::class)->get('cache.refresh_ahead_percent'),
            'observability' => app(StorefrontCacheObservability::class)->summary(),
        ];
    }

    private function requestRebuild(CacheRebuildDomain $domain): void
    {
        $run = app(StorefrontCacheRebuildService::class)->request($domain, auth()->id());

        Notification::make()
            ->title('درخواست بازسازی کش ثبت شد')
            ->body("عملیات #{$run->id} به‌صورت غیرهم‌زمان در صف cache-rebuild قرار گرفت.")
            ->success()
            ->send();
    }
}

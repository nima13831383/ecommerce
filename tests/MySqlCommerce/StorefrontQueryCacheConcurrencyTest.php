<?php

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('allows one cache builder across six concurrent isolated MySQL requests', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $setting = Setting::query()->create([
        'group' => 'qa',
        'key' => 'storefront_cache_builder_'.str()->uuid(),
        'value' => '0',
        'type' => 'integer',
        'is_public' => false,
    ]);
    DB::commit();

    $result = app(ConcurrentProcessRunner::class)->run('storefront_cache_rebuild', [
        'setting_id' => $setting->id,
        'probe' => (string) str()->uuid(),
        'sleep_us' => 3000000,
        'workers' => ['A', 'B', 'C', 'D', 'E', 'F'],
    ]);

    expect($result['alive'])->toBeTrue()
        ->and(collect($result['results'])->pluck('json.ok')->unique()->all())->toBe([true])
        ->and(collect($result['results'])->map(fn (array $result): ?string => $result['json']['result']['value'] ?? null)->unique()->values()->all())->toBe(['rebuilt-once'])
        ->and((int) $setting->fresh()->value)->toBe(1);
});

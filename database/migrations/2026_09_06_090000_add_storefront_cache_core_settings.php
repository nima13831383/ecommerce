<?php

use App\Settings\SettingRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'cache.store',
            'cache.products.ttl_seconds',
            'cache.blog.ttl_seconds',
            'cache.lock_seconds',
            'cache.stale_seconds',
        ] as $key) {
            $definition = SettingRegistry::get($key);

            DB::table('settings')->insertOrIgnore([
                'group' => $definition->group,
                'key' => $definition->key,
                'value' => (string) $definition->default,
                'type' => $definition->type,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Core settings are additive operational structure and intentionally retained.
    }
};

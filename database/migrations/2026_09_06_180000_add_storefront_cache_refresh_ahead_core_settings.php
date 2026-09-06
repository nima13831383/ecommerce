<?php

use App\Settings\SettingRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['cache.refresh_ahead.enabled', 'cache.refresh_ahead_percent'] as $key) {
            $definition = SettingRegistry::get($key);
            DB::table('settings')->insertOrIgnore([
                'group' => $definition->group, 'key' => $definition->key, 'value' => (string) $definition->default,
                'type' => $definition->type, 'is_public' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void {}
};

<?php

use App\Settings\SettingRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $definition = SettingRegistry::get('branding.logo_path');

        DB::table('settings')->insertOrIgnore([
            'group' => $definition->group,
            'key' => $definition->key,
            'value' => null,
            'type' => $definition->type,
            'is_public' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Core setting rows are intentionally preserved on rollback.
    }
};

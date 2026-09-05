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
        $now = now();

        foreach ([
            'payment.default_gateway',
            'payment.zarinpal.enabled',
            'payment.zarinpal.sandbox',
            'payment.zarinpal.merchant_id',
        ] as $key) {
            $definition = SettingRegistry::get($key);

            DB::table('settings')->insertOrIgnore([
                'group' => $definition->group,
                'key' => $definition->key,
                'value' => $definition->default === null ? null : ($definition->default === true ? '1' : ($definition->default === false ? '0' : (string) $definition->default)),
                'type' => $definition->type,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Core setting rows are intentionally retained so operator values are never deleted.
    }
};

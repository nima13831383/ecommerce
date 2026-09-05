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
            'auth.customer_auth_mode',
            'auth.otp.code_length',
            'auth.otp.ttl_seconds',
            'auth.otp.resend_cooldown_seconds',
            'auth.otp.max_attempts',
            'sms.default_provider',
            'sms.smsir.enabled',
            'sms.smsir.sandbox',
            'sms.smsir.api_key',
            'sms.smsir.verify_template_id',
            'sms.smsir.verify_parameter_name',
        ] as $key) {
            $definition = SettingRegistry::get($key);
            $value = match (true) {
                $definition->default === null => null,
                is_bool($definition->default) => $definition->default ? '1' : '0',
                default => (string) $definition->default,
            };

            DB::table('settings')->insertOrIgnore([
                'group' => $definition->group,
                'key' => $definition->key,
                'value' => $value,
                'type' => $definition->type,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Core settings are additive operational structure and intentionally preserved.
    }
};

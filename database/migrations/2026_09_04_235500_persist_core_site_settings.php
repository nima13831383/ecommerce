<?php

use App\Settings\SettingRegistry;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (SettingRegistry::coreDefinitions() as $definition) {
            DB::table('settings')->insertOrIgnore([
                'group' => $definition->group,
                'key' => $definition->key,
                'value' => $this->serialize($definition->default),
                'type' => $definition->type,
                'is_public' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Core setting rows are intentionally retained on rollback so operator values are never deleted.
    }

    private function serialize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => $value ? '1' : '0',
            default => (string) $value,
        };
    }
};

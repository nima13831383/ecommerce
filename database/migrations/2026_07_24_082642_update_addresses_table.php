<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('addresses', 'company')) {
                $table->string('company')->nullable()->after('last_name'); // WooCommerce parity
            }
        });

        Schema::table('addresses', function (Blueprint $table) {
            $table->index(['user_id', 'type'], 'addresses_user_id_type_index');
            $table->index('province_id', 'addresses_province_id_index');
            $table->index('city_id', 'addresses_city_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropIndex('addresses_user_id_type_index');
            $table->dropIndex('addresses_province_id_index');
            $table->dropIndex('addresses_city_id_index');

            if (Schema::hasColumn('addresses', 'company')) {
                $table->dropColumn('company');
            }
        });
    }
};

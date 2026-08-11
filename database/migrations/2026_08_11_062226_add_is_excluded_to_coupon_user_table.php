<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_add_is_excluded_to_coupon_user_table.php
    public function up(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->boolean('is_excluded')->default(false)->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_user', function (Blueprint $table) {
            $table->dropColumn('is_excluded');
        });
    }
};

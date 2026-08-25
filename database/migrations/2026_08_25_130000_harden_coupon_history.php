<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table): void {
            $table->softDeletes();
        });

        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->unique(['coupon_id', 'order_id'], 'coupon_usages_coupon_order_unique');
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table): void {
            $table->dropUnique('coupon_usages_coupon_order_unique');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->json('coupon_snapshot')->nullable()->after('coupon_id');
            $table->json('shipping_snapshot')->nullable()->after('shipping_method_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['coupon_snapshot', 'shipping_snapshot']);
        });
    }
};

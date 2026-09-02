<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('volume', 15, 6)->nullable()->after('weight');
            $table->string('parcel_type', 32)->default('normal')->after('volume');
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->decimal('volume', 15, 6)->nullable()->after('weight');
        });

        Schema::table('coupons', function (Blueprint $table): void {
            $table->boolean('exclude_discounted_products')->default(false)->after('exclude_sale_items');
            $table->dropColumn('free_shipping');
        });

        Schema::create('coupon_role', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->boolean('is_excluded')->default(false);
            $table->primary(['coupon_id', 'role_id']);
            $table->index('role_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_role');

        Schema::table('coupons', function (Blueprint $table): void {
            $table->boolean('free_shipping')->default(false);
            $table->dropColumn('exclude_discounted_products');
        });

        Schema::table('product_variations', function (Blueprint $table): void {
            $table->dropColumn('volume');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['volume', 'parcel_type']);
        });
    }
};

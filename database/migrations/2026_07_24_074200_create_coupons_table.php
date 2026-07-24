<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // create_coupons_table
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();

            // نوع تخفیف: percent | fixed_cart | fixed_product
            $table->enum('type', ['percent', 'fixed_cart', 'fixed_product'])->default('fixed_cart');
            $table->decimal('amount', 15, 0)->default(0);

            // ارسال رایگان همراه کوپن
            $table->boolean('free_shipping')->default(false);

            // محدودیت‌های سبد
            $table->decimal('min_spend', 15, 0)->nullable();
            $table->decimal('max_spend', 15, 0)->nullable();
            $table->decimal('max_discount', 15, 0)->nullable(); // سقف مبلغ تخفیف برای درصدی

            // محدودیت استفاده
            $table->unsignedInteger('usage_limit')->nullable();        // کل دفعات
            $table->unsignedInteger('usage_limit_per_user')->nullable();
            $table->unsignedInteger('usage_count')->default(0);

            // شمول
            $table->boolean('individual_use_only')->default(false); // ترکیب‌نشدن با کوپن دیگر
            $table->boolean('exclude_sale_items')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

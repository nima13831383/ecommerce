<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_class_id')->nullable()->constrained()->nullOnDelete();

            // هزینه‌ها (IRR)
            $table->decimal('base_cost', 15, 0)->default(0);        // هزینه‌ی پایه
            $table->decimal('cost_per_kg', 15, 0)->default(0);      // برای calc_type=weight
            $table->decimal('cost_per_item', 15, 0)->default(0);    // برای per_item

            // آستانه‌ها (بازه‌ی اعتبار این تعرفه)
            $table->decimal('min_order_total', 15, 0)->nullable();  // حداقل مبلغ سبد برای اعمال
            $table->decimal('max_order_total', 15, 0)->nullable();
            $table->decimal('min_weight', 10, 3)->nullable();       // کیلوگرم
            $table->decimal('max_weight', 10, 3)->nullable();

            // رایگان بالای این مبلغ
            $table->decimal('free_over', 15, 0)->nullable();

            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['shipping_zone_id', 'shipping_method_id'], 'ship_rate_zone_method_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_rates');
    }
};

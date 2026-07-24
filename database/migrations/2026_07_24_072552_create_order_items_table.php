<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // ارجاع نرم به محصول (nullOnDelete تا حذف محصول، تاریخچه فاکتور را خراب نکند)
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained()->nullOnDelete();

            // snapshot اطلاعات محصول در لحظه‌ی خرید
            $table->string('product_name', 255);       // نام ثابت روی فاکتور
            $table->string('sku', 100)->nullable();    // کد کالای لحظه‌ی خرید
            $table->json('variation_attributes')->nullable(); // مثل {رنگ: قرمز, سایز: L}

            // مقادیر
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 0);      // قیمت واحد snapshot
            $table->decimal('discount_amount', 15, 0)->default(0); // تخفیف ردیف
            $table->decimal('tax_amount', 15, 0)->default(0);      // مالیات ردیف §9
            $table->decimal('line_total', 15, 0);      // (unit_price*qty) - discount + tax

            $table->timestamps();

            $table->index(['order_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->string('sku')->nullable()->unique();

            // قیمت مستقل هر تنوع
            $table->decimal('price', 15, 0)->default(0);
            $table->decimal('sale_price', 15, 0)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();

            // موجودی مستقل هر تنوع
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])
                ->default('in_stock');

            // وزن و ابعاد اختصاصی تنوع (در صورت تفاوت با محصول اصلی)
            $table->decimal('weight', 10, 2)->nullable();

            // تصویر اختصاصی تنوع (مثلاً تصویر رنگ مشخص)
            $table->string('image')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['product_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};

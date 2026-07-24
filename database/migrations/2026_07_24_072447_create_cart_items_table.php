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
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()   // محصول متغیر §2.1
                ->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 15, 0);        // snapshot قیمت لحظه‌ی افزودن
            $table->decimal('line_total', 15, 0);        // unit_price * quantity
            $table->json('options')->nullable();         // ویژگی‌های انتخابی/متادیتا
            $table->timestamps();

            $table->unique(['cart_id', 'product_id', 'product_variation_id'], 'cart_item_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};

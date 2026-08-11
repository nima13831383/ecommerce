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
        Schema::create('cart_coupon', function (Blueprint $table) {
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->decimal('discount_amount', 15, 0)->default(0);
            $table->unsignedTinyInteger('sort_order')->default(0); // ترتیب اعمال

            $table->primary(['cart_id', 'coupon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_coupon');
    }
};

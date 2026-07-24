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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // کاربر مهمان => null
            $table->string('token', 64)->nullable()->index();     // شناسه سبد مهمان (cookie/session)
            $table->string('currency', 3)->default('IRR');        // §4 چندارزی
            $table->enum('status', ['active', 'abandoned', 'converted', 'merged'])
                ->default('active')->index();
            $table->foreignId('coupon_id')->nullable();           // FK بدون constraint تا §8 کوپن
            $table->decimal('subtotal', 15, 0)->default(0);       // مجموع قبل از تخفیف/مالیات
            $table->decimal('discount_total', 15, 0)->default(0);
            $table->decimal('tax_total', 15, 0)->default(0);      // پرشدن در §9
            $table->decimal('shipping_total', 15, 0)->default(0); // پرشدن در §7
            $table->decimal('grand_total', 15, 0)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index(); // §13 بازیابی سبد رهاشده
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};

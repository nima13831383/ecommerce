<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 32)->unique();          // شماره فاکتور نمایشی
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cart_id')->nullable()->constrained()->nullOnDelete();

            // وضعیت‌ها
            $table->enum('status', [
                'pending',
                'awaiting_payment',
                'processing',
                'shipped',
                'delivered',
                'completed',
                'cancelled',
                'refunded',
                'failed',
            ])->default('pending')->index();
            $table->enum('payment_status', [
                'unpaid',
                'partially_paid',
                'paid',
                'refunded',
                'partially_refunded',
            ])->default('unpaid')->index();

            // اطلاعات مشتری (snapshot - مستقل از تغییر پروفایل)
            $table->string('customer_name', 150);
            $table->string('customer_mobile', 20)->index();
            $table->string('customer_email', 150)->nullable();
            $table->string('national_id', 20)->nullable();          // برای فاکتور رسمی §9

            // آدرس‌ها (snapshot به‌صورت JSON - مستقل از حذف آدرس §10)
            $table->json('billing_address')->nullable();
            $table->json('shipping_address')->nullable();

            // مبالغ
            $table->string('currency', 3)->default('IRR');
            $table->decimal('items_subtotal', 15, 0)->default(0);   // جمع اقلام قبل تخفیف
            $table->decimal('discount_total', 15, 0)->default(0);   // §8 کوپن/§13 تخفیف
            $table->decimal('tax_total', 15, 0)->default(0);        // §9 مالیات
            $table->decimal('shipping_total', 15, 0)->default(0);   // §7 حمل‌ونقل
            $table->decimal('grand_total', 15, 0)->default(0);      // مبلغ نهایی قابل پرداخت
            $table->decimal('paid_total', 15, 0)->default(0);       // جمع پرداخت‌شده §5
            $table->decimal('refunded_total', 15, 0)->default(0);   // جمع مرجوعی

            // ارجاعات ماژول‌های آینده (FK بدون constraint - طبق تصمیم قبلی)
            $table->unsignedBigInteger('coupon_id')->nullable();            // §8
            $table->unsignedBigInteger('shipping_method_id')->nullable();   // §7
            $table->string('tracking_number', 100)->nullable();            // کد رهگیری پستی

            // متادیتا
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->text('customer_note')->nullable();     // یادداشت مشتری
            $table->text('admin_note')->nullable();        // یادداشت داخلی ادمین

            // زمان‌بندی
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

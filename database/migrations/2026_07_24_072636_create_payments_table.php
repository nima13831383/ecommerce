<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_number', 40)->unique();          // شناسه‌ی نمایشی پرداخت
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // روش و درگاه
            $table->enum('method', [
                'online_gateway',   // درگاه بانکی
                'wallet',           // کیف پول §3
                'cod',              // پرداخت در محل
                'bank_transfer',    // کارت‌به‌کارت/فیش
                'manual',           // ثبت دستی توسط ادمین
            ])->default('online_gateway')->index();
            $table->string('gateway', 50)->nullable();               // مثل zarinpal, mellat, sadad

            // وضعیت
            $table->enum('status', [
                'pending',      // ایجاد شده، منتظر هدایت به درگاه
                'processing',   // کاربر در درگاه است / منتظر تأیید
                'paid',         // موفق و تأییدشده
                'failed',       // ناموفق
                'cancelled',    // لغو توسط کاربر
                'expired',      // منقضی
                'refunded',     // مرجوع کامل
                'partially_refunded',
            ])->default('pending')->index();

            // مبالغ
            $table->string('currency', 3)->default('IRR');
            $table->decimal('amount', 15, 0);                        // مبلغ درخواستی این پرداخت
            $table->decimal('paid_amount', 15, 0)->default(0);       // مبلغ واقعی تأییدشده
            $table->decimal('refunded_amount', 15, 0)->default(0);   // مبلغ مرجوع‌شده

            // شناسه‌های درگاه
            $table->string('authority', 100)->nullable()->index();   // توکن/authority درگاه
            $table->string('reference_id', 100)->nullable();         // شماره پیگیری بانک (RRN)
            $table->string('card_pan', 20)->nullable();              // ۶ رقم اول+۴ آخر (masked)
            $table->string('card_hash', 64)->nullable();             // هش کارت برای تطبیق

            // پاسخ خام درگاه (برای دیباگ و ممیزی)
            $table->json('gateway_response')->nullable();
            $table->string('failure_reason', 255)->nullable();

            // زمان‌بندی
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();             // مهلت اعتبار لینک پرداخت

            // متادیتا
            $table->string('ip_address', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

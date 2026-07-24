<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();

            // نوع تعامل - هر مرحله از گفتگو با درگاه یک ردیف می‌شود
            $table->enum('type', [
                'request',      // درخواست ایجاد تراکنش (پرداخت‌شروع)
                'callback',     // بازگشت از درگاه
                'verify',       // تأیید نهایی نزد بانک
                'inquiry',      // استعلام وضعیت
                'refund',       // مرجوعی
                'reverse',      // برگشت تراکنش تأییدنشده
            ])->index();

            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');

            $table->decimal('amount', 15, 0)->default(0);

            // شناسه‌ها
            $table->string('authority', 100)->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('gateway_status_code', 20)->nullable();   // کد وضعیت بازگشتی درگاه

            // payload کامل رفت و برگشت (idempotency/ممیزی)
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            $table->text('message')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'type']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

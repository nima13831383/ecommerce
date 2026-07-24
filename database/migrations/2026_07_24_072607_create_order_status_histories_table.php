<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // چه کسی وضعیت را تغییر داد (سیستم => null)
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('from_status', 30)->nullable(); // وضعیت قبلی
            $table->string('to_status', 30);               // وضعیت جدید
            $table->enum('type', ['status', 'payment_status'])->default('status');

            $table->text('comment')->nullable();           // دلیل/توضیح تغییر
            $table->boolean('notify_customer')->default(false); // آیا به مشتری اطلاع داده شد

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};

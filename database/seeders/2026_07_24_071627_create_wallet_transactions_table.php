<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('wallet_id')
                ->constrained('wallets')
                ->cascadeOnDelete();

            // واریز یا برداشت
            $table->enum('type', ['deposit', 'withdraw']);

            $table->decimal('amount', 15, 0);
            // موجودی پس از تراکنش (برای audit و صورتحساب)
            $table->decimal('balance_after', 15, 0);

            // منشأ تراکنش: شارژ، خرید، بازگشت وجه، تعدیل ادمین
            $table->enum('reason', [
                'charge',
                'purchase',
                'refund',
                'cashback',
                'admin_adjustment',
                'withdrawal_request'
            ])->default('charge');

            // ارجاع پلی‌مورفیک به منبع (سفارش، پرداخت و...)
            $table->nullableMorphs('reference');

            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])
                ->default('completed');

            $table->timestamps();

            $table->index(['wallet_id', 'type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};

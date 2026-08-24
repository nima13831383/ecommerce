<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('initiation_idempotency_key', 100)->nullable();
            $table->string('initiation_fingerprint', 64)->nullable();
            $table->boolean('reconciliation_required')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->unique(['order_id', 'initiation_idempotency_key'], 'payment_order_initiation_key_unique');
            $table->index(['gateway', 'authority']);
            $table->index(['gateway', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique('payment_order_initiation_key_unique');
            $table->dropIndex(['gateway', 'authority']);
            $table->dropIndex(['gateway', 'reference_id']);
            $table->dropColumn(['initiation_idempotency_key', 'initiation_fingerprint', 'reconciliation_required', 'verified_at']);
        });
    }
};

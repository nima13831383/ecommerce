<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('channel', 30);
            $table->json('recipient_snapshot')->nullable();
            $table->json('payload_snapshot')->nullable();
            $table->string('status', 20);
            $table->string('idempotency_key', 150)->unique('customer_notifications_key_unique');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at'], 'customer_notifications_status_created_index');
            $table->index(['type', 'created_at'], 'customer_notifications_type_created_index');
            $table->index('order_id', 'customer_notifications_order_index');
            $table->index('user_id', 'customer_notifications_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_notifications');
    }
};

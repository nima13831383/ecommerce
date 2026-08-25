<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('inventory_owner_type');
            $table->unsignedBigInteger('inventory_owner_id');
            $table->string('operation', 40);
            $table->integer('quantity_delta');
            $table->unsignedInteger('quantity_before');
            $table->unsignedInteger('quantity_after');
            $table->string('reference_type')->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(
                ['inventory_owner_type', 'inventory_owner_id', 'created_at'],
                'inv_tx_owner_created_idx',
            );
            $table->unique(['inventory_owner_type', 'inventory_owner_id', 'operation', 'reference_type', 'reference_id'], 'inventory_transaction_reference_unique');
        });

        Schema::create('inventory_reservations', function (Blueprint $table): void {
            $table->id();
            $table->string('inventory_owner_type');
            $table->unsignedBigInteger('inventory_owner_id');
            $table->unsignedInteger('quantity');
            $table->string('status', 20);
            $table->string('reference_type')->nullable();
            $table->string('reference_id', 100)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(
                ['inventory_owner_type', 'inventory_owner_id', 'status', 'expires_at'],
                'inv_res_owner_status_exp_idx',
            );
            $table->unique(['inventory_owner_type', 'inventory_owner_id', 'reference_type', 'reference_id'], 'inventory_reservation_reference_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('inventory_transactions');
    }
};

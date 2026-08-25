<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->index(['reference_type', 'reference_id'], 'inventory_reservations_reference_index');
            $table->index(['status', 'expires_at'], 'inventory_reservations_status_expiry_index');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->index(['reference_type', 'reference_id'], 'inventory_transactions_reference_index');
            $table->index(['operation', 'created_at'], 'inventory_transactions_operation_created_index');
            $table->index('created_by', 'inventory_transactions_created_by_index');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_reservations', function (Blueprint $table): void {
            $table->dropIndex('inventory_reservations_reference_index');
            $table->dropIndex('inventory_reservations_status_expiry_index');
        });

        Schema::table('inventory_transactions', function (Blueprint $table): void {
            $table->dropIndex('inventory_transactions_reference_index');
            $table->dropIndex('inventory_transactions_operation_created_index');
            $table->dropIndex('inventory_transactions_created_by_index');
        });
    }
};

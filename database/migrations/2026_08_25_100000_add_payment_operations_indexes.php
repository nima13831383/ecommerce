<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->index('reconciliation_required');
            $table->index('verified_at');
            $table->index('reference_id');
            $table->index(['reconciliation_required', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex(['reconciliation_required']);
            $table->dropIndex(['verified_at']);
            $table->dropIndex(['reference_id']);
            $table->dropIndex(['reconciliation_required', 'created_at']);
        });
    }
};

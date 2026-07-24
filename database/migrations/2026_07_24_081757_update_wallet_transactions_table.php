<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('wallet_transactions', 'direction')) {
                $table->enum('direction', ['credit', 'debit'])->after('type');
            }

            if (!Schema::hasColumn('wallet_transactions', 'balance_before')) {
                $table->decimal('balance_before', 15, 0)->after('amount');
            }

            if (!Schema::hasColumn('wallet_transactions', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('wallet_transactions', 'meta')) {
                $table->json('meta')->nullable()->after('description');
            }
        });

        // index روی direction برای گزارش‌گیری credit/debit
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->index('direction', 'wallet_transactions_direction_index');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('wallet_transactions_direction_index');

            foreach (['direction', 'balance_before', 'reversed_at', 'meta'] as $col) {
                if (Schema::hasColumn('wallet_transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};


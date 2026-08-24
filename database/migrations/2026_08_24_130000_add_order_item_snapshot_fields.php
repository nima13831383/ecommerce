<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('line_subtotal', 15, 0)->default(0)->after('unit_price');
            $table->json('tax_snapshot')->nullable()->after('tax_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropColumn(['line_subtotal', 'tax_snapshot']);
        });
    }
};

<?php
// database/migrations/xxxx_restructure_tax_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tax_rates');

        Schema::table('tax_classes', function (Blueprint $table) {
            $table->string('description')->nullable()->after('slug');

            $table->enum('type', ['percent', 'fixed'])
                ->default('percent')
                ->after('description');

            $table->decimal('value', 15, 3)->default(0)->after('type');
            $table->boolean('is_active')->default(true)->after('is_default');

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::table('tax_classes', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['is_active', 'is_default']);
            $table->dropColumn(['description', 'type', 'value', 'is_active']);
        });
    }
};

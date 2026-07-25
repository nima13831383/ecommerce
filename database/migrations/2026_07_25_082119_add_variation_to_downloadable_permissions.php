<?php

// database/migrations/2026_07_25_000005_add_variation_to_downloadable_permissions.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('downloadable_permissions', function (Blueprint $table) {
            $table->foreignId('variation_id')->nullable()->after('product_id')
                ->constrained('product_variations')->nullOnDelete();

            // منبع فایل: کدام رکورد product_downloads این گرنت را ساخت
            $table->foreignId('product_download_id')->nullable()->after('variation_id')
                ->constrained('product_downloads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('downloadable_permissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variation_id');
            $table->dropConstrainedForeignId('product_download_id');
        });
    }
};

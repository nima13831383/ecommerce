<?php
// database/migrations/2026_07_25_081306_add_type_columns_to_products.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'external_url')) {
                $table->string('external_url')->nullable()->after('is_virtual');
            }
            if (! Schema::hasColumn('products', 'button_text')) {
                $table->string('button_text')->nullable()->after('external_url');
            }
            if (! Schema::hasColumn('products', 'download_limit')) {
                $table->unsignedInteger('download_limit')->nullable()->after('button_text');
            }
            if (! Schema::hasColumn('products', 'download_expiry')) {
                $table->unsignedInteger('download_expiry')->nullable()->after('download_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            foreach (['external_url', 'button_text', 'download_limit', 'download_expiry'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

<?php

// database/migrations/xxxx_add_tax_class_id_to_products_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->foreign('tax_class_id')
                ->references('id')->on('tax_classes')
                ->nullOnDelete();

            $t->index('tax_class_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->dropForeign(['tax_class_id']);
            $t->dropIndex(['tax_class_id']);
        });
    }
};

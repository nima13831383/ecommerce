<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // database/migrations/xxxx_cleanup_product_attribute_columns.php
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('variation_attributes'); // با pivot جایگزین شد
        });

        Schema::table('attribute_product', function (Blueprint $table) {
            // این دو ستون override سطح-محصول بودند و هیچ‌وقت پر نمی‌شدند؛
            // منبع حقیقت همان attributes.is_variation / is_visible است
            $table->dropColumn(['is_variation', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::table('products', fn(Blueprint $t) => $t->longText('variation_attributes')->nullable());
        Schema::table('attribute_product', function (Blueprint $t) {
            $t->boolean('is_variation')->default(false);
            $t->boolean('is_visible')->default(true);
        });
    }
};

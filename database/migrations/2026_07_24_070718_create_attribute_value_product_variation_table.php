<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_value_product_variation', function (Blueprint $table) {
            $table->foreignId('product_variation_id')
                ->constrained('product_variations')
                ->cascadeOnDelete();

            $table->foreignId('attribute_value_id')
                ->constrained('attribute_values')
                ->cascadeOnDelete();

            $table->primary(
                ['product_variation_id', 'attribute_value_id'],
                'pv_av_primary'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_value_product_variation');
    }
};

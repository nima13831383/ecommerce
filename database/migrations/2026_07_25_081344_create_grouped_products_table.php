<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grouped_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('products')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained('products')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['parent_id', 'child_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grouped_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')
                ->cascadeOnUpdate()->cascadeOnDelete();
            // واریشن‌ها هم می‌توانند فایل مستقل داشته باشند (اختیاری)
            $table->foreignId('variation_id')->nullable()
                ->constrained('product_variations')
                ->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('name');
            $table->string('file_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_downloads');
    }
};

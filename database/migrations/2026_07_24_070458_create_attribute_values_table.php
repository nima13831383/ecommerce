<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();

            $table->string('value');           // مثل: قرمز، XL
            $table->string('slug');

            // برای نمایش Swatch رنگی یا تصویری
            $table->string('color_code', 9)->nullable();  // مثل #FF0000
            $table->string('image')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // جلوگیری از تکرار مقدار در یک ویژگی
            $table->unique(['attribute_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
    }
};

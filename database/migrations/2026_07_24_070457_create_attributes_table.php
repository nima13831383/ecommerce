<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // مثل: رنگ، سایز، جنس
            $table->string('slug')->unique();

            // نوع نمایش ویژگی در فرانت
            $table->enum('type', ['select', 'color', 'button', 'radio', 'image'])
                ->default('select');

            // آیا این ویژگی برای ساخت تنوع (Variation) استفاده می‌شود؟
            $table->boolean('is_variation')->default(false);

            // نمایش در صفحه محصول
            $table->boolean('is_visible')->default(true);

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_variation', 'is_visible']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributes');
    }
};

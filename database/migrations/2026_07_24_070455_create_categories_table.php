<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            // ساختار سلسله‌مراتبی والد/فرزند (خودارجاع)
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // تصویر و آیکون اختصاصی دسته
            $table->string('image')->nullable();
            $table->string('icon')->nullable();

            // متادیتای سئو
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            // ترتیب نمایش سفارشی
            $table->unsignedInteger('sort_order')->default(0);

            // وضعیت‌ها: فعال، ویژه، مخفی
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_hidden')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌ها برای بهبود کوئری‌های پرکاربرد
            $table->index('parent_id');
            $table->index(['is_active', 'is_hidden']);
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

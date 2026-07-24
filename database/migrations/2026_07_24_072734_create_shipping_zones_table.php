<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 120)->unique();

            // نوع تطبیق منطقه
            $table->enum('match_type', [
                'everywhere',   // پیش‌فرض/سایر مناطق
                'province',     // بر اساس استان
                'city',         // بر اساس شهر
                'postal_code',  // بر اساس بازه‌ی کد پستی
                'country',      // بین‌الملل
            ])->default('province')->index();

            // لیست مقادیر تطبیق (کد استان‌ها/شهرها/کشورها یا بازه‌ی کدپستی)
            $table->json('regions')->nullable();

            $table->unsignedInteger('priority')->default(0);   // اولویت تطبیق؛ بالاتر = ابتدا بررسی
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_zones');
    }
};

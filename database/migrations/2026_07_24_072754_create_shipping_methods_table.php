<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);                        // پست پیشتاز
            $table->string('slug', 120)->unique();              // post_pishtaz
            $table->string('carrier', 50)->nullable();          // post, tipax, snapp, mahex
            $table->string('logo', 255)->nullable();

            // نحوه‌ی محاسبه‌ی هزینه
            $table->enum('calc_type', [
                'flat',         // نرخ ثابت
                'weight',       // بر اساس وزن
                'price',        // بر اساس مبلغ سبد (پلکانی)
                'free',         // رایگان
                'per_item',     // به‌ازای هر قلم
            ])->default('flat')->index();

            $table->boolean('requires_tracking')->default(true); // نیاز به کد رهگیری
            $table->boolean('is_pickup')->default(false);        // تحویل حضوری؟
            $table->boolean('is_cod_available')->default(true);  // پرداخت در محل مجاز؟

            // تخمین زمان تحویل (روز)
            $table->unsignedSmallInteger('estimated_days_min')->nullable();
            $table->unsignedSmallInteger('estimated_days_max')->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_methods');
    }
};

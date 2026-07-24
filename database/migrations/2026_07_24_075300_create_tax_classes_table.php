<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // database/migrations/xxxx_create_tax_classes_table.php
        Schema::create('tax_classes', function (Blueprint $t) {
            $t->id();
            $t->string('name');              // استاندارد، معاف، نرخ کاهش‌یافته
            $t->string('slug')->unique();
            $t->boolean('is_default')->default(false);
            $t->timestamps();
        });

        // xxxx_create_tax_rates_table.php
        Schema::create('tax_rates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tax_class_id')->constrained()->cascadeOnDelete();
            $t->string('country', 2)->default('IR');
            $t->string('state')->nullable();      // استان (اختیاری برای مالیات منطقه‌ای)
            $t->string('city')->nullable();
            $t->string('name');                   // «مالیات بر ارزش افزوده»
            $t->decimal('rate', 6, 3);            // درصد، مثلا 9.000
            $t->boolean('compound')->default(false); // آیا روی مالیات‌های قبلی محاسبه شود
            $t->boolean('shipping_taxable')->default(false);
            $t->unsignedSmallInteger('priority')->default(1);
            $t->timestamps();

            $t->index(['tax_class_id', 'country', 'state']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_classes');
    }
};

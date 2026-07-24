<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // نوع آدرس: ارسال یا صورتحساب
            $table->enum('type', ['shipping', 'billing', 'both'])->default('both');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('mobile', 15);

            // مکان (ارجاع به جداول تقسیمات کشوری در بخش ۵)
            $table->unsignedBigInteger('province_id')->nullable();
            $table->unsignedBigInteger('city_id')->nullable();

            $table->string('postal_code', 10)->nullable();
            $table->text('address_line');
            $table->string('plaque', 10)->nullable();
            $table->string('unit', 10)->nullable();

            // مختصات جغرافیایی (نقشه)
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->boolean('is_default')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};

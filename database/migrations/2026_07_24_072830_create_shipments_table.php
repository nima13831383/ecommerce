<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->string('shipment_number', 40)->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shipping_method_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot روش حمل (نام درگاه ممکن است بعداً حذف شود)
            $table->string('method_name', 100)->nullable();
            $table->string('carrier', 50)->nullable();

            $table->enum('status', [
                'pending',      // در انتظار پردازش
                'processing',   // در حال بسته‌بندی
                'ready',        // آماده‌ی تحویل به حامل
                'shipped',      // تحویل حامل شد
                'in_transit',   // در مسیر
                'delivered',    // تحویل داده شد
                'returned',     // مرجوع
                'cancelled',
            ])->default('pending')->index();

            $table->string('tracking_code', 100)->nullable()->index();
            $table->string('tracking_url', 255)->nullable();

            $table->decimal('shipping_cost', 15, 0)->default(0);   // هزینه‌ی محاسبه‌شده
            $table->decimal('weight', 10, 3)->nullable();          // وزن کل مرسوله (kg)

            // Snapshot آدرس مقصد (مستقل از تغییرات بعدی addresses)
            $table->json('shipping_address')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            // برند (اختیاری، با حفظ محصول در صورت حذف برند)
            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            // نوع محصول
            $table->enum('type', ['simple', 'variable', 'grouped', 'external', 'downloadable'])
                ->default('simple');

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku')->nullable()->unique();

            // توضیحات کوتاه و کامل
            $table->text('short_description')->nullable();
            $table->longText('description')->nullable();

            // قیمت‌گذاری (برای محصول ساده؛ در متغیر روی تنوع‌ها ست می‌شود)
            $table->decimal('price', 15, 0)->default(0);
            $table->decimal('sale_price', 15, 0)->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();

            // موجودی و انبار
            $table->boolean('manage_stock')->default(true);
            $table->integer('stock_quantity')->default(0);
            $table->enum('stock_status', ['in_stock', 'out_of_stock', 'on_backorder'])
                ->default('in_stock');
            $table->unsignedInteger('low_stock_threshold')->nullable();

            // محصول دانلودی
            $table->boolean('is_downloadable')->default(false);
            $table->boolean('is_virtual')->default(false);

            // فیزیکی: وزن و ابعاد برای محاسبه هزینه ارسال
            $table->decimal('weight', 10, 2)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();

            // کلاس مالیات و ارسال (کلید خارجی در بخش‌های بعدی افزوده می‌شود)
            $table->unsignedBigInteger('tax_class_id')->nullable();
            $table->unsignedBigInteger('shipping_class_id')->nullable();

            // محصول خارجی/وابسته
            $table->string('external_url')->nullable();
            $table->string('button_text')->nullable();

            // آمار و امتیاز
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedBigInteger('sales_count')->default(0);
            $table->decimal('rating_avg', 3, 2)->default(0);
            $table->unsignedInteger('rating_count')->default(0);

            // متادیتای سئو
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            // وضعیت‌ها
            $table->enum('status', ['draft', 'published', 'pending', 'private'])
                ->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // ایندکس‌های پرکاربرد در فیلتر و لیست
            $table->index(['status', 'is_featured']);
            $table->index(['type', 'stock_status']);
            $table->index('price');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

<?php

namespace Database\Seeders;

use App\Enums\InventoryOperation;
use App\Models\Product;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $brandId = DB::table('brands')->where('slug', 'apple')->value('id');
        $categoryId = DB::table('categories')->where('slug', 'موبایل')->value('id')
            ?? DB::table('categories')->first()->id;

        // ---------- محصول ساده ----------
        $simpleId = DB::table('products')->insertGetId([
            'brand_id' => $brandId,
            'type' => 'simple',
            'name' => 'کابل شارژ USB-C',
            'slug' => 'usb-c-cable',
            'sku' => 'CBL-USBC-001',
            'short_description' => 'کابل شارژ سریع یک متری',
            'price' => 250000,
            'sale_price' => 199000,
            'stock_quantity' => 0,
            'stock_status' => 'out_of_stock',
            'status' => 'published',
            'is_featured' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('category_product')->insert([
            'category_id' => $categoryId,
            'product_id' => $simpleId,
        ]);

        app(InventoryService::class)->setOnHand(Product::findOrFail($simpleId), 120, InventoryOperation::OpeningStock);

        DB::table('product_images')->insert([
            'product_id' => $simpleId,
            'path' => 'products/usb-c-cable.jpg',
            'alt' => 'کابل شارژ USB-C',
            'is_primary' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---------- محصول متغیر ----------
        $variableId = DB::table('products')->insertGetId([
            'brand_id' => $brandId,
            'type' => 'variable',
            'name' => 'تیشرت نخی',
            'slug' => 'cotton-tshirt',
            'sku' => 'TSH-001',
            'short_description' => 'تیشرت نخی در رنگ و سایز متنوع',
            'price' => 0, // قیمت روی تنوع‌ها
            'manage_stock' => false,
            'status' => 'published',
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('category_product')->insert([
            'category_id' => $categoryId,
            'product_id' => $variableId,
        ]);

        // ویژگی‌های مبنای تنوع
        $colorAttr = DB::table('attributes')->where('slug', 'color')->value('id');
        $sizeAttr = DB::table('attributes')->where('slug', 'size')->value('id');

        DB::table('attribute_product')->insert([
            ['product_id' => $variableId, 'attribute_id' => $colorAttr, 'sort_order' => 0],
            ['product_id' => $variableId, 'attribute_id' => $sizeAttr,  'sort_order' => 1],
        ]);

        // مقادیر مورد نیاز
        $red = DB::table('attribute_values')->where('attribute_id', $colorAttr)->where('slug', 'like', '%')->where('value', 'قرمز')->value('id');
        $black = DB::table('attribute_values')->where('attribute_id', $colorAttr)->where('value', 'مشکی')->value('id');
        $sizeM = DB::table('attribute_values')->where('attribute_id', $sizeAttr)->where('value', 'M')->value('id');
        $sizeL = DB::table('attribute_values')->where('attribute_id', $sizeAttr)->where('value', 'L')->value('id');

        // تنوع‌ها: قرمز/M و مشکی/L
        $combos = [
            ['color' => $red,   'size' => $sizeM, 'sku' => 'TSH-RED-M',   'price' => 450000, 'stock' => 30],
            ['color' => $black, 'size' => $sizeL, 'sku' => 'TSH-BLK-L',   'price' => 470000, 'stock' => 20],
        ];

        DB::table('attribute_value_product')->insert([
            ['product_id' => $variableId, 'attribute_value_id' => $red],
            ['product_id' => $variableId, 'attribute_value_id' => $black],
            ['product_id' => $variableId, 'attribute_value_id' => $sizeM],
            ['product_id' => $variableId, 'attribute_value_id' => $sizeL],
        ]);

        $product = Product::findOrFail($variableId);
        $variantService = app(ProductVariantService::class);

        foreach ($combos as $combo) {
            $variantService->create($product, [
                'sku' => $combo['sku'],
                'price' => $combo['price'],
                'stock_quantity' => $combo['stock'],
                'stock_status' => 'in_stock',
                'is_active' => true,
            ], [$combo['color'], $combo['size']]);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $brandId = DB::table('brands')->where('slug', 'apple')->value('id');
        $categoryId = DB::table('categories')->where('slug', 'موبایل')->value('id')
            ?? DB::table('categories')->first()->id;

        // ---------- محصول ساده ----------
        $simpleId = DB::table('products')->insertGetId([
            'brand_id'      => $brandId,
            'type'          => 'simple',
            'name'          => 'کابل شارژ USB-C',
            'slug'          => 'usb-c-cable',
            'sku'           => 'CBL-USBC-001',
            'short_description' => 'کابل شارژ سریع یک متری',
            'price'         => 250000,
            'sale_price'    => 199000,
            'stock_quantity' => 120,
            'stock_status'  => 'in_stock',
            'status'        => 'published',
            'is_featured'   => true,
            'published_at'  => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        DB::table('category_product')->insert([
            'category_id' => $categoryId,
            'product_id'  => $simpleId,
        ]);

        DB::table('product_images')->insert([
            'product_id' => $simpleId,
            'path'       => 'products/usb-c-cable.jpg',
            'alt'        => 'کابل شارژ USB-C',
            'is_primary' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ---------- محصول متغیر ----------
        $variableId = DB::table('products')->insertGetId([
            'brand_id'     => $brandId,
            'type'         => 'variable',
            'name'         => 'تیشرت نخی',
            'slug'         => 'cotton-tshirt',
            'sku'          => 'TSH-001',
            'short_description' => 'تیشرت نخی در رنگ و سایز متنوع',
            'price'        => 0, // قیمت روی تنوع‌ها
            'manage_stock' => false,
            'status'       => 'published',
            'published_at' => now(),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        DB::table('category_product')->insert([
            'category_id' => $categoryId,
            'product_id'  => $variableId,
        ]);

        // ویژگی‌های مبنای تنوع
        $colorAttr = DB::table('attributes')->where('slug', 'color')->value('id');
        $sizeAttr  = DB::table('attributes')->where('slug', 'size')->value('id');

        DB::table('attribute_product')->insert([
            ['product_id' => $variableId, 'attribute_id' => $colorAttr, 'is_variation' => true, 'is_visible' => true, 'sort_order' => 0],
            ['product_id' => $variableId, 'attribute_id' => $sizeAttr,  'is_variation' => true, 'is_visible' => true, 'sort_order' => 1],
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

        foreach ($combos as $combo) {
            $variationId = DB::table('product_variations')->insertGetId([
                'product_id'     => $variableId,
                'sku'            => $combo['sku'],
                'price'          => $combo['price'],
                'stock_quantity' => $combo['stock'],
                'stock_status'   => 'in_stock',
                'is_active'      => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            DB::table('attribute_value_product_variation')->insert([
                ['product_variation_id' => $variationId, 'attribute_value_id' => $combo['color']],
                ['product_variation_id' => $variationId, 'attribute_value_id' => $combo['size']],
            ]);
        }
    }
}

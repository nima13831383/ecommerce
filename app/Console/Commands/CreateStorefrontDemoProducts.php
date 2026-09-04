<?php

namespace App\Console\Commands;

use App\Enums\InventoryOperation;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Models\Tag;
use App\Models\TaxClass;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CreateStorefrontDemoProducts extends Command
{
    protected $signature = 'demo:storefront-products';

    protected $description = 'Create the idempotent local storefront demo catalog.';

    /** @var array<string, int> */
    private array $counts = [
        'categories_created' => 0,
        'categories_reused' => 0,
        'brands_created' => 0,
        'brands_reused' => 0,
        'tags_created' => 0,
        'tags_reused' => 0,
        'products_created' => 0,
        'products_reused' => 0,
        'attributes_created' => 0,
        'attributes_reused' => 0,
        'values_created' => 0,
        'values_reused' => 0,
        'variations_created' => 0,
        'variations_reused' => 0,
        'images_created' => 0,
        'images_reused' => 0,
        'inventory_adjustments' => 0,
    ];

    public function handle(ProductVariantService $variants, InventoryService $inventory): int
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            $this->error('This development-only command is allowed only in local, development, or testing environments.');

            return self::FAILURE;
        }

        $this->info('Reconciling storefront demo catalog...');

        $catalog = $this->catalogSupport();
        $taxClass = TaxClass::query()->where('is_active', true)->orderByDesc('is_default')->first();

        $perfume = $this->upsertProduct($this->perfumeDefinition($catalog, $taxClass), $inventory);
        $this->syncVariableProduct($perfume, $this->perfumeAttributes(), $this->perfumeVariations(), $variants);
        $this->syncImages($perfume, $this->perfumeImages());

        $bracelet = $this->upsertProduct($this->braceletDefinition($catalog, $taxClass), $inventory);
        $this->syncVariableProduct($bracelet, $this->braceletAttributes(), $this->braceletVariations(), $variants);
        $this->syncImages($bracelet, $this->braceletImages());

        $serumDefinition = $this->simpleDefinition(
            catalog: $catalog,
            taxClass: $taxClass,
            key: 'serum',
            name: 'سرم آبرسان Hydra Glow',
            slug: 'demo-hydra-glow-serum',
            sku: 'DEMO-SERUM-001',
            category: 'skincare',
            brand: 'maison-noir',
            price: 12_500_000,
            stock: 15,
            image: ['filename' => 'primary', 'alt' => 'سرم آبرسان Hydra Glow', 'start' => '#d8f3dc', 'end' => '#40916c', 'label' => 'Hydra Glow'],
            salePrice: 9_900_000,
        );
        $serum = $this->upsertProduct($serumDefinition, $inventory);
        $this->syncImages($serum, [$serumDefinition['image']]);

        $lipstickDefinition = $this->simpleDefinition(
            catalog: $catalog,
            taxClass: $taxClass,
            key: 'lipstick',
            name: 'رژ لب Velvet Rose',
            slug: 'demo-velvet-rose-lipstick',
            sku: 'DEMO-LIPSTICK-001',
            category: 'cosmetics',
            brand: 'maison-noir',
            price: 7_500_000,
            stock: 20,
            image: ['filename' => 'primary', 'alt' => 'رژ لب Velvet Rose', 'start' => '#f8ad9d', 'end' => '#9d0208', 'label' => 'Velvet Rose'],
        );
        $lipstick = $this->upsertProduct($lipstickDefinition, $inventory);
        $this->syncImages($lipstick, [$lipstickDefinition['image']]);

        $pocketDefinition = $this->simpleDefinition(
            catalog: $catalog,
            taxClass: $taxClass,
            key: 'pocket-perfume',
            name: 'عطر جیبی Midnight',
            slug: 'demo-midnight-pocket-perfume',
            sku: 'DEMO-POCKET-PERFUME-001',
            category: 'perfume',
            brand: 'aurora',
            price: 4_900_000,
            stock: 0,
            image: ['filename' => 'primary', 'alt' => 'عطر جیبی Midnight', 'start' => '#1d3557', 'end' => '#457b9d', 'label' => 'Midnight'],
        );
        $pocketPerfume = $this->upsertProduct($pocketDefinition, $inventory);
        $this->syncImages($pocketPerfume, [$pocketDefinition['image']]);

        $this->upsertProduct($this->hiddenDefinition($catalog, $taxClass), $inventory);

        $this->info('Demo catalog reconciliation complete.');
        foreach ($this->counts as $key => $count) {
            $this->line("{$key}: {$count}");
        }

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function catalogSupport(): array
    {
        $categories = [
            'perfume' => $this->supportCategory('عطر و ادکلن', 'perfume'),
            'accessories' => $this->supportCategory('اکسسوری', 'accessories'),
            'cosmetics' => $this->supportCategory('لوازم آرایشی', 'cosmetics'),
            'skincare' => $this->supportCategory('مراقبت پوست', 'skincare'),
        ];

        $brands = [
            'aurora' => $this->supportBrand('Aurora', 'aurora'),
            'lumiere' => $this->supportBrand('Lumière', 'lumiere'),
            'maison-noir' => $this->supportBrand('Maison Noir', 'maison-noir'),
        ];

        $tags = [
            'new' => $this->supportTag('جدید', 'demo-new'),
            'variable' => $this->supportTag('محصول متغیر', 'demo-variable'),
            'sale' => $this->supportTag('پیشنهاد ویژه', 'demo-sale'),
        ];

        return compact('categories', 'brands', 'tags');
    }

    private function supportCategory(string $name, string $slug): Category
    {
        $category = Category::withTrashed()->where('slug', $slug)->first();
        if ($category && $category->name !== $name) {
            throw new DomainException("Category slug `{$slug}` belongs to another record.");
        }

        if (! $category) {
            $this->counts['categories_created']++;

            return tap(Category::create([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'is_hidden' => false,
            ]), fn () => $this->line("Created category {$name}."));
        }

        if ($category->trashed()) {
            $category->restore();
        }

        $this->counts['categories_reused']++;

        return $category;
    }

    private function supportBrand(string $name, string $slug): Brand
    {
        $brand = Brand::withTrashed()->where('slug', $slug)->first();
        if ($brand && $brand->name !== $name) {
            throw new DomainException("Brand slug `{$slug}` belongs to another record.");
        }

        if (! $brand) {
            $this->counts['brands_created']++;

            return Brand::create(['name' => $name, 'slug' => $slug, 'is_active' => true]);
        }

        if ($brand->trashed()) {
            $brand->restore();
        }

        $this->counts['brands_reused']++;

        return $brand;
    }

    private function supportTag(string $name, string $slug): Tag
    {
        $tag = Tag::query()->where('slug', $slug)->first();
        if ($tag && $tag->name !== $name) {
            throw new DomainException("Tag slug `{$slug}` belongs to another record.");
        }

        if (! $tag) {
            $this->counts['tags_created']++;

            return Tag::create(['name' => $name, 'slug' => $slug]);
        }

        $this->counts['tags_reused']++;

        return $tag;
    }

    /** @param array<string, mixed> $definition */
    private function upsertProduct(array $definition, InventoryService $inventory): Product
    {
        $matches = Product::withTrashed()
            ->where(fn ($query) => $query->where('sku', $definition['sku'])->orWhere('slug', $definition['slug']))
            ->get();

        if ($matches->count() > 1) {
            throw new DomainException("Demo identifiers for `{$definition['slug']}` are not unique.");
        }

        $product = $matches->first();
        $isNew = $product === null;

        if ($product && ($product->name !== $definition['name'] || $product->sku !== $definition['sku'] || $product->slug !== $definition['slug'])) {
            throw new DomainException("A non-demo product already uses a demo identifier for `{$definition['slug']}`.");
        }

        if (! $product) {
            $product = new Product;
            $this->counts['products_created']++;
        } else {
            $this->counts['products_reused']++;
            if ($product->trashed()) {
                $product->restore();
            }
        }

        $product->fill([
            'brand_id' => $definition['brand_id'],
            'type' => $definition['type'],
            'name' => $definition['name'],
            'slug' => $definition['slug'],
            'sku' => $definition['sku'],
            'short_description' => $definition['short_description'],
            'description' => $definition['description'],
            'price' => $definition['price'],
            'sale_price' => $definition['sale_price'],
            'sale_starts_at' => $definition['sale_starts_at'],
            'sale_ends_at' => $definition['sale_ends_at'],
            'manage_stock' => $definition['manage_stock'],
            'weight' => $definition['weight'],
            'volume' => $definition['volume'],
            'parcel_type' => $definition['parcel_type'],
            'tax_class_id' => $definition['tax_class_id'],
            'status' => $definition['status'],
            'is_featured' => $definition['is_featured'],
            'published_at' => $definition['published_at'],
        ]);
        $product->save();

        $product->categories()->syncWithoutDetaching([$definition['category_id']]);
        $product->tags()->syncWithoutDetaching(collect($definition['tag_ids'])->all());

        if ($isNew || $product->type === 'variable') {
            $product->forceFill([
                'stock_quantity' => 0,
                'stock_status' => 'out_of_stock',
            ])->save();
        }

        if ($product->type !== 'variable') {
            $transaction = $inventory->setOnHand(
                $product->fresh(),
                $definition['stock'],
                $isNew ? InventoryOperation::OpeningStock : InventoryOperation::ManualAdjustment,
            );
            $this->counts['inventory_adjustments'] += $transaction ? 1 : 0;
        }

        return $product->fresh();
    }

    /** @param array<string, mixed> $definition */
    private function syncVariableProduct(Product $product, array $attributeDefinitions, array $variationDefinitions, ProductVariantService $variants): void
    {
        $attributeIds = [];
        $valueIds = [];
        $valueIdsBySlug = [];

        foreach ($attributeDefinitions as $sortOrder => $definition) {
            $attribute = $this->supportAttribute($definition['name'], $definition['slug'], $sortOrder);
            $attributeIds[$attribute->id] = ['sort_order' => $sortOrder];

            foreach ($definition['values'] as $valueSortOrder => $valueDefinition) {
                $value = $this->supportAttributeValue($attribute, $valueDefinition, $valueSortOrder);
                $valueIds[] = $value->id;
                $valueIdsBySlug[$valueDefinition['slug']] = $value->id;
            }
        }

        $product->attributes()->sync($attributeIds);
        $product->attributeValues()->sync($valueIds);

        foreach ($variationDefinitions as $variationDefinition) {
            $variationDefinition['attribute_value_ids'] = array_map(
                fn (string $slug): int => $valueIdsBySlug[$slug],
                $variationDefinition['attribute_value_slugs'],
            );
            $this->syncVariation($product, $variationDefinition, $variants);
        }
    }

    private function supportAttribute(string $name, string $slug, int $sortOrder): Attribute
    {
        $attribute = Attribute::query()->where('slug', $slug)->first();
        if ($attribute && $attribute->name !== $name) {
            throw new DomainException("Attribute slug `{$slug}` belongs to another record.");
        }

        if (! $attribute) {
            $this->counts['attributes_created']++;

            return Attribute::create([
                'name' => $name,
                'slug' => $slug,
                'type' => 'select',
                'is_variation' => true,
                'is_visible' => true,
                'sort_order' => $sortOrder,
            ]);
        }

        $this->counts['attributes_reused']++;

        return $attribute->fill([
            'type' => 'select',
            'is_variation' => true,
            'is_visible' => true,
            'sort_order' => $sortOrder,
        ])->save() ? $attribute->fresh() : $attribute;
    }

    /** @param array<string, string> $definition */
    private function supportAttributeValue(Attribute $attribute, array $definition, int $sortOrder): AttributeValue
    {
        $value = AttributeValue::query()
            ->where('attribute_id', $attribute->id)
            ->where('slug', $definition['slug'])
            ->first();

        if ($value && $value->value !== $definition['value']) {
            throw new DomainException("Attribute value slug `{$definition['slug']}` belongs to another record.");
        }

        if (! $value) {
            $this->counts['values_created']++;

            return AttributeValue::create([
                'attribute_id' => $attribute->id,
                'value' => $definition['value'],
                'slug' => $definition['slug'],
                'sort_order' => $sortOrder,
            ]);
        }

        $this->counts['values_reused']++;

        return $value->fill(['sort_order' => $sortOrder])->save() ? $value->fresh() : $value;
    }

    /** @param array<string, mixed> $definition */
    private function syncVariation(Product $product, array $definition, ProductVariantService $variants): ProductVariation
    {
        $signature = $variants->combinationSignature($product, $definition['attribute_value_ids']);
        $variation = $product->variations()->where('combination_signature', $signature)->first();
        $skuVariation = ProductVariation::query()->where('sku', $definition['sku'])->first();
        $skuProduct = Product::query()->where('sku', $definition['sku'])->first();

        if ($skuProduct && $skuProduct->isNot($product)) {
            throw new DomainException("Variation SKU `{$definition['sku']}` belongs to another product.");
        }

        if ($skuVariation && $skuVariation->product_id !== $product->id) {
            throw new DomainException("Variation SKU `{$definition['sku']}` belongs to another product.");
        }

        if ($variation && $variation->sku !== $definition['sku']) {
            throw new DomainException("Combination `{$signature}` already belongs to another demo variation.");
        }

        if ($variation && $skuVariation && $variation->isNot($skuVariation)) {
            throw new DomainException("Variation SKU `{$definition['sku']}` and signature `{$signature}` conflict.");
        }

        $attributes = [
            'sku' => $definition['sku'],
            'price' => $definition['price'],
            'sale_price' => $definition['sale_price'] ?? null,
            'sale_starts_at' => $definition['sale_starts_at'] ?? null,
            'sale_ends_at' => $definition['sale_ends_at'] ?? null,
            'manage_stock' => true,
            'stock_quantity' => $definition['stock'],
            'weight' => $definition['weight'],
            'volume' => $definition['volume'],
            'is_active' => true,
            'is_dismissed' => false,
        ];

        if ($variation) {
            $this->counts['variations_reused']++;

            return $variants->update($variation, $attributes, $definition['attribute_value_ids']);
        }

        $this->counts['variations_created']++;

        return $variants->create($product, $attributes, $definition['attribute_value_ids']);
    }

    /** @param array<int, array<string, mixed>> $images */
    private function syncImages(Product $product, array $images): void
    {
        $disk = Storage::disk(ProductImage::storageDisk());
        $paths = collect($images)->map(fn (array $image): string => "storefront-demo/{$product->slug}/{$image['filename']}.svg")->all();

        $product->images()->whereNotIn('path', $paths)->update(['is_primary' => false]);

        foreach ($images as $sortOrder => $image) {
            $path = "storefront-demo/{$product->slug}/{$image['filename']}.svg";
            $disk->put($path, $this->placeholderSvg($image['label'], $image['alt'], $image['start'], $image['end']));
            $record = ProductImage::query()->where('product_id', $product->id)->where('path', $path)->first();
            $attributes = [
                'alt' => $image['alt'],
                'is_primary' => $sortOrder === 0,
                'sort_order' => $sortOrder,
            ];

            if ($record) {
                $this->counts['images_reused']++;
                $record->fill($attributes)->save();

                continue;
            }

            $this->counts['images_created']++;
            ProductImage::create(['product_id' => $product->id, 'path' => $path, ...$attributes]);
        }
    }

    /** @return array<string, mixed> */
    private function perfumeDefinition(array $catalog, ?TaxClass $taxClass): array
    {
        return [
            'brand_id' => $catalog['brands']['aurora']->id,
            'category_id' => $catalog['categories']['perfume']->id,
            'tag_ids' => [$catalog['tags']['new']->id, $catalog['tags']['variable']->id],
            'type' => 'variable',
            'name' => 'ادو پرفیوم Aurora Velvet',
            'slug' => 'demo-aurora-velvet-perfume',
            'sku' => 'DEMO-PERFUME-001',
            'short_description' => 'رایحه‌ای مخملی و ماندگار برای استفاده روزانه و مهمانی.',
            'description' => 'ادو پرفیوم Aurora Velvet با ترکیبی متعادل از نت‌های گل‌فام، چوبی و مشک، انتخابی شیک برای شروع روز یا یک شب خاص است. شیشه‌ی مقاوم و بسته‌بندی دقیق آن، تجربه‌ای مناسب برای هدیه‌دادن فراهم می‌کند.',
            'price' => 0,
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'manage_stock' => false,
            'weight' => 0.4,
            'volume' => 420,
            'parcel_type' => 'fragile',
            'tax_class_id' => $taxClass?->id,
            'status' => 'published',
            'is_featured' => true,
            'published_at' => now(),
            'stock' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function braceletDefinition(array $catalog, ?TaxClass $taxClass): array
    {
        return [
            'brand_id' => $catalog['brands']['lumiere']->id,
            'category_id' => $catalog['categories']['accessories']->id,
            'tag_ids' => [$catalog['tags']['new']->id, $catalog['tags']['variable']->id],
            'type' => 'variable',
            'name' => 'دستبند استیل Luna',
            'slug' => 'demo-luna-steel-bracelet',
            'sku' => 'DEMO-BRACELET-001',
            'short_description' => 'دستبند استیل سبک با سه رنگ ماندگار و سه سایز کاربردی.',
            'description' => 'دستبند استیل Luna با طراحی مینیمال، آبکاری بادوام و قابلیت انتخاب رنگ و سایز، برای استفاده روزمره و ست‌کردن با اکسسوری‌های دیگر ساخته شده است.',
            'price' => 0,
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'manage_stock' => false,
            'weight' => 0.06,
            'volume' => 80,
            'parcel_type' => 'normal',
            'tax_class_id' => $taxClass?->id,
            'status' => 'published',
            'is_featured' => true,
            'published_at' => now(),
            'stock' => 0,
        ];
    }

    /** @return array<string, mixed> */
    private function simpleDefinition(array $catalog, ?TaxClass $taxClass, string $key, string $name, string $slug, string $sku, string $category, string $brand, int $price, int $stock, array $image, ?int $salePrice = null): array
    {
        return [
            'brand_id' => $catalog['brands'][$brand]->id,
            'category_id' => $catalog['categories'][$category]->id,
            'tag_ids' => array_values(array_filter([$catalog['tags']['new']->id, $salePrice ? $catalog['tags']['sale']->id : null])),
            'type' => 'simple',
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku,
            'short_description' => "{$name} برای تست نمایش محصول ساده در فروشگاه.",
            'description' => "{$name} با مشخصات قابل اتکا و بسته‌بندی مناسب، برای نمایش سناریوهای واقعی فروشگاه آماده شده است.",
            'price' => $price,
            'sale_price' => $salePrice,
            'sale_starts_at' => $salePrice ? now()->subDay() : null,
            'sale_ends_at' => $salePrice ? now()->addYear() : null,
            'manage_stock' => true,
            'weight' => $key === 'serum' ? 0.3 : 0.08,
            'volume' => $key === 'serum' ? 260 : 70,
            'parcel_type' => 'normal',
            'tax_class_id' => $taxClass?->id,
            'status' => 'published',
            'is_featured' => true,
            'published_at' => now(),
            'stock' => $stock,
            'image' => $image,
        ];
    }

    /** @return array<string, mixed> */
    private function hiddenDefinition(array $catalog, ?TaxClass $taxClass): array
    {
        return [
            'brand_id' => $catalog['brands']['maison-noir']->id,
            'category_id' => $catalog['categories']['cosmetics']->id,
            'tag_ids' => [],
            'type' => 'simple',
            'name' => 'محصول مخفی تستی',
            'slug' => 'demo-hidden-product',
            'sku' => 'DEMO-HIDDEN-001',
            'short_description' => 'محصول کنترل برای بررسی فیلتر وضعیت انتشار.',
            'description' => 'این محصول عمداً منتشر نشده است و نباید در صفحات عمومی فروشگاه نمایش داده شود.',
            'price' => 1_000_000,
            'sale_price' => null,
            'sale_starts_at' => null,
            'sale_ends_at' => null,
            'manage_stock' => true,
            'weight' => 0.1,
            'volume' => 80,
            'parcel_type' => 'normal',
            'tax_class_id' => $taxClass?->id,
            'status' => 'draft',
            'is_featured' => false,
            'published_at' => null,
            'stock' => 0,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function perfumeAttributes(): array
    {
        return [
            ['name' => 'حجم', 'slug' => 'demo-perfume-volume', 'values' => [
                ['value' => '30 میلی‌لیتر', 'slug' => '30ml'],
                ['value' => '50 میلی‌لیتر', 'slug' => '50ml'],
                ['value' => '100 میلی‌لیتر', 'slug' => '100ml'],
            ]],
            ['name' => 'نوع بسته‌بندی', 'slug' => 'demo-perfume-packaging', 'values' => [
                ['value' => 'استاندارد', 'slug' => 'standard'],
                ['value' => 'کادویی', 'slug' => 'gift'],
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function braceletAttributes(): array
    {
        return [
            ['name' => 'رنگ', 'slug' => 'demo-bracelet-color', 'values' => [
                ['value' => 'طلایی', 'slug' => 'gold'],
                ['value' => 'نقره‌ای', 'slug' => 'silver'],
                ['value' => 'رزگلد', 'slug' => 'rose-gold'],
            ]],
            ['name' => 'سایز', 'slug' => 'demo-bracelet-size', 'values' => [
                ['value' => 'Small', 'slug' => 'small'],
                ['value' => 'Medium', 'slug' => 'medium'],
                ['value' => 'Large', 'slug' => 'large'],
            ]],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function perfumeVariations(): array
    {
        return $this->variationMatrix([
            1 => ['30ml', '50ml', '100ml'],
            2 => ['standard', 'gift'],
        ], [
            '30ml-standard' => ['sku' => 'DEMO-PERFUME-30-STANDARD', 'price' => 18_500_000, 'stock' => 8, 'weight' => 0.28, 'volume' => 180],
            '30ml-gift' => ['sku' => 'DEMO-PERFUME-30-GIFT', 'price' => 20_500_000, 'stock' => 3, 'weight' => 0.34, 'volume' => 220],
            '50ml-standard' => ['sku' => 'DEMO-PERFUME-50-STANDARD', 'price' => 24_900_000, 'stock' => 12, 'weight' => 0.36, 'volume' => 250],
            '50ml-gift' => ['sku' => 'DEMO-PERFUME-50-GIFT', 'price' => 27_500_000, 'stock' => 0, 'weight' => 0.42, 'volume' => 300],
            '100ml-standard' => ['sku' => 'DEMO-PERFUME-100-STANDARD', 'price' => 36_900_000, 'stock' => 5, 'weight' => 0.52, 'volume' => 420],
            '100ml-gift' => ['sku' => 'DEMO-PERFUME-100-GIFT', 'price' => 40_500_000, 'stock' => 2, 'weight' => 0.62, 'volume' => 500],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function braceletVariations(): array
    {
        return $this->variationMatrix([
            1 => ['gold', 'silver', 'rose-gold'],
            2 => ['small', 'medium', 'large'],
        ], [
            'gold-small' => ['sku' => 'DEMO-BRACELET-GOLD-S', 'price' => 7_500_000, 'sale_price' => 6_900_000, 'stock' => 6, 'weight' => 0.05, 'volume' => 60],
            'gold-medium' => ['sku' => 'DEMO-BRACELET-GOLD-M', 'price' => 7_900_000, 'stock' => 5, 'weight' => 0.06, 'volume' => 70],
            'gold-large' => ['sku' => 'DEMO-BRACELET-GOLD-L', 'price' => 8_300_000, 'stock' => 4, 'weight' => 0.07, 'volume' => 80],
            'silver-small' => ['sku' => 'DEMO-BRACELET-SILVER-S', 'price' => 6_900_000, 'stock' => 8, 'weight' => 0.05, 'volume' => 60],
            'silver-medium' => ['sku' => 'DEMO-BRACELET-SILVER-M', 'price' => 7_400_000, 'stock' => 0, 'weight' => 0.06, 'volume' => 70],
            'silver-large' => ['sku' => 'DEMO-BRACELET-SILVER-L', 'price' => 7_800_000, 'stock' => 3, 'weight' => 0.07, 'volume' => 80],
            'rose-gold-small' => ['sku' => 'DEMO-BRACELET-ROSE-S', 'price' => 7_800_000, 'stock' => 5, 'weight' => 0.05, 'volume' => 60],
            'rose-gold-medium' => ['sku' => 'DEMO-BRACELET-ROSE-M', 'price' => 8_400_000, 'stock' => 4, 'weight' => 0.06, 'volume' => 70],
            'rose-gold-large' => ['sku' => 'DEMO-BRACELET-ROSE-L', 'price' => 8_900_000, 'stock' => 2, 'weight' => 0.07, 'volume' => 80],
        ]);
    }

    /** @param array<int, array<int, string>> $axes @param array<string, array<string, mixed>> $data @return array<int, array<string, mixed>> */
    private function variationMatrix(array $axes, array $data): array
    {
        $result = [];
        foreach ($this->cartesian(array_values($axes)) as $valueSlugs) {
            $key = implode('-', $valueSlugs);
            $definition = $data[$key];
            $result[] = ['attribute_value_slugs' => $valueSlugs, ...$definition];
        }

        return $result;
    }

    /** @param array<int, array<int, string>> $sets @return array<int, array<int, string>> */
    private function cartesian(array $sets): array
    {
        $result = [[]];
        foreach ($sets as $set) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($set as $value) {
                    $next[] = [...$prefix, $value];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /** @return array<int, array<string, string>> */
    private function perfumeImages(): array
    {
        return [
            ['filename' => 'primary', 'alt' => 'ادو پرفیوم Aurora Velvet', 'start' => '#ead7c3', 'end' => '#9c6644', 'label' => 'Aurora Velvet'],
            ['filename' => 'bottle', 'alt' => 'بطری ادو پرفیوم Aurora Velvet', 'start' => '#fefae0', 'end' => '#dda15e', 'label' => 'Signature bottle'],
            ['filename' => 'gift-box', 'alt' => 'بسته‌بندی کادویی Aurora Velvet', 'start' => '#e9c46a', 'end' => '#6d597a', 'label' => 'Gift edition'],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function braceletImages(): array
    {
        return [
            ['filename' => 'primary', 'alt' => 'دستبند استیل Luna', 'start' => '#f1faee', 'end' => '#588157', 'label' => 'Luna steel'],
            ['filename' => 'detail', 'alt' => 'جزئیات دستبند استیل Luna', 'start' => '#e9ecef', 'end' => '#6c757d', 'label' => 'Color details'],
        ];
    }

    private function placeholderSvg(string $label, string $alt, string $start, string $end): string
    {
        $label = htmlspecialchars($label, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $alt = htmlspecialchars($alt, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="900" height="900" viewBox="0 0 900 900" role="img" aria-label="{$alt}">
    <defs>
        <linearGradient id="background" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="{$start}"/>
            <stop offset="100%" stop-color="{$end}"/>
        </linearGradient>
    </defs>
    <rect width="900" height="900" rx="52" fill="url(#background)"/>
    <circle cx="450" cy="350" r="190" fill="#ffffff" fill-opacity="0.28"/>
    <rect x="285" y="210" width="330" height="285" rx="34" fill="#ffffff" fill-opacity="0.82" transform="rotate(-8 450 350)"/>
    <circle cx="450" cy="350" r="95" fill="#ffffff" fill-opacity="0.4"/>
    <text x="450" y="650" text-anchor="middle" font-family="Tahoma, Arial, sans-serif" font-size="42" font-weight="700" fill="#17202a">{$label}</text>
    <text x="450" y="710" text-anchor="middle" font-family="Arial, sans-serif" font-size="20" letter-spacing="4" fill="#17202a" fill-opacity="0.7">STOREFRONT DEMO</text>
</svg>
SVG;
    }
}

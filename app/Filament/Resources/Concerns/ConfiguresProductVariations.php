<?php
// app/Filament/Resources/Products/Concerns/ConfiguresProductVariations.php
namespace App\Filament\Resources\Products\Concerns;

use App\Filament\Resources\Products\Schemas\ProductForm;
use App\Models\Product;
use Illuminate\Support\Arr;

trait ConfiguresProductVariations
{
    protected array $variationAttributesState = [];
    protected array $variationsState = [];

    /** state ریپیترها را از $data جدا می‌کند تا Eloquent سعی نکند ستون بسازد */
    protected function extractVariationState(array $data): array
    {
        $this->variationAttributesState = Arr::pull($data, 'variation_attributes') ?? [];
        $this->variationsState          = Arr::pull($data, 'variations') ?? [];

        return $data;
    }

    // protected function persistVariations(Product $product): void
    // {
    //     if ($product->type !== 'variable') {
    //         return;
    //     }

    //     // ۱) attribute_product
    //     $sync = [];
    //     foreach (array_values($this->variationAttributesState) as $i => $row) {
    //         if (empty($row['attribute_id'])) {
    //             continue;
    //         }
    //         $sync[(int) $row['attribute_id']] = ['sort_order' => $i];
    //     }
    //     $product->attributes()->sync($sync);

    //     // ۲) product_variations + attribute_value_product_variation
    //     $keptIds = [];

    //     foreach ($this->variationsState as $row) {
    //         $valueIds = ProductForm::normalizeIds($row['attribute_value_ids'] ?? '');
    //         if (empty($valueIds)) {
    //             continue;
    //         }

    //         $isDismissed = (bool) ($row['is_dismissed'] ?? false);

    //         $variation = filled($row['id'] ?? null)
    //             ? $product->variations()->find($row['id']) ?? $product->variations()->make()
    //             : $product->variations()->make();

    //         $variation->fill([
    //             'sku'            => $row['sku'] ?? null,
    //             'price'          => $row['price'] ?? 0,
    //             'sale_price'     => $row['sale_price'] ?? null,
    //             'stock_quantity' => $row['stock_quantity'] ?? 0,
    //             'is_active'      => $isDismissed ? false : (bool) ($row['is_active'] ?? true),
    //             'is_dismissed'   => $isDismissed,
    //         ])->product()->associate($product);

    //         $variation->save();

    //         // ترکیب همیشه ذخیره می‌شود؛ حتی برای dismiss شده‌ها تا قابل بازگردانی باشد
    //         $variation->attributeValues()->sync($valueIds);

    //         $keptIds[] = $variation->id;
    //     }

    //     // هرچه از repeater ناپدید شده → غیرفعال، نه حذف فیزیکی
    //     $product->variations()
    //         ->when($keptIds, fn($q) => $q->whereNotIn('id', $keptIds))
    //         ->update(['is_active' => false, 'is_dismissed' => true]);
    // }

    protected function persistVariations(Product $product): void
    {
        if ($product->type !== 'variable') {
            return;
        }

        // ۱) attribute_product
        $sync = [];
        foreach (array_values($this->variationAttributesState) as $i => $row) {
            if (empty($row['attribute_id'])) {
                continue;
            }
            $sync[(int) $row['attribute_id']] = ['sort_order' => $i];
        }
        $product->attributes()->sync($sync);

        // ۱-ب) attribute_value_product ← انتخاب‌های سطح محصول (Step 1)
        $selectedValueIds = collect($this->variationAttributesState)
            ->flatMap(fn($row) => ProductForm::normalizeIds($row['value_ids'] ?? ''))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $product->attributeValues()->sync($selectedValueIds);

        // ۲) product_variations + attribute_value_product_variation
        $keptIds = [];

        foreach ($this->variationsState as $row) {
            $valueIds = ProductForm::normalizeIds($row['attribute_value_ids'] ?? '');
            if (empty($valueIds)) {
                continue;
            }

            $isDismissed = (bool) ($row['is_dismissed'] ?? false);

            /** @var \App\Models\ProductVariation $variation */
            $variation = filled($row['id'] ?? null)
                ? $product->variations()->find($row['id']) ?? $product->variations()->make()
                : $product->variations()->make();

            $variation->fill([
                'sku'            => $row['sku'] ?? null,
                'price'          => $row['price'] ?? 0,
                'sale_price'     => $row['sale_price'] ?? null,
                'stock_quantity' => $row['stock_quantity'] ?? 0,
                'is_active'      => $isDismissed ? false : (bool) ($row['is_active'] ?? true),
                'is_dismissed'   => $isDismissed,
            ])->product()->associate($product);

            $variation->save();

            // ترکیب همیشه ذخیره می‌شود، حتی برای dismiss شده‌ها تا قابل بازگردانی باشد
            $variation->attributeValues()->sync($valueIds);

            $keptIds[] = $variation->id;
        }

    // ۳) هرچه از repeater ناپدید شده
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductVariation> $stale */
        $stale = $product->variations()
            ->when($keptIds, fn($q) => $q->whereNotIn('id', $keptIds))
            ->withCount('orderItems')
            ->get();

        foreach ($stale as $variation) {
            if ($variation->order_items_count > 0) {
                $variation->update(['is_active' => false, 'is_dismissed' => true]);
                continue;
            }

            $variation->attributeValues()->detach();
            $variation->delete();
        }
    }



    /** پرکردن فرم در صفحه Edit از جداول واسط */
    protected function hydrateVariationState(array $data, Product $product): array
    {
        if ($product->type !== 'variable') {
            return $data;
        }

        $product->load(['attributes', 'variations.attributeValues']);

        $valuesByAttribute = $product->variations
            ->flatMap(fn($v) => $v->attributeValues)
            ->groupBy('attribute_id')
            ->map(fn($g) => $g->pluck('id')->unique()->values()->all());

        $data['variation_attributes'] = $product->attributes
            ->map(fn($a) => [
                'attribute_id' => $a->id,
                'value_ids'    => $valuesByAttribute->get($a->id, []),
            ])->values()->all();

        $data['variations'] = $product->variations
            ->map(fn($v) => [
                'id'                  => $v->id,
                'attribute_value_ids' => $v->attributeValues->pluck('id')->implode(','),
                'sku'                 => $v->sku,
                'price'               => $v->price,
                'sale_price'          => $v->sale_price,
                'stock_quantity'      => $v->stock_quantity,
                'is_active'           => $v->is_active,
                'is_dismissed'        => $v->is_dismissed,
            ])->values()->all();

        return $data;
    }
}

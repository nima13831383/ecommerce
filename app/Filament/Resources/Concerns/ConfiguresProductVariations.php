<?php

namespace App\Filament\Resources\Concerns;

use App\Enums\InventoryOperation;
use App\Models\Product;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Arr;

trait ConfiguresProductVariations
{
    protected array $variationAttributesState = [];

    protected array $variationsState = [];

    protected ?int $requestedStockQuantity = null;

    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    protected function extractVariationState(array $data): array
    {
        $this->requestedStockQuantity = array_key_exists('stock_quantity', $data) ? (int) Arr::pull($data, 'stock_quantity') : null;
        Arr::forget($data, 'stock_status');
        $this->variationAttributesState = Arr::pull($data, 'variation_attributes') ?? [];
        $this->variationsState = Arr::pull($data, 'variations') ?? [];

        return $data;
    }

    protected function persistProductInventory(Product $product, bool $opening): void
    {
        if ($product->type === 'variable' || $this->requestedStockQuantity === null) {
            return;
        }
        app(InventoryService::class)->setOnHand($product, $this->requestedStockQuantity, $opening ? InventoryOperation::OpeningStock : InventoryOperation::ManualAdjustment);
    }

    protected function persistVariations(Product $product): void
    {
        if ($product->type !== 'variable') {
            return;
        }

        app(ProductVariantService::class)->synchronize(
            $product,
            $this->variationAttributesState,
            $this->variationsState,
        );
    }

    protected function hydrateVariationState(array $data, Product $product): array
    {
        if ($product->type !== 'variable') {
            return $data;
        }

        $product->load(['attributes', 'variations.attributeValues']);

        $valuesByAttribute = $product->variations
            ->flatMap(fn ($variation) => $variation->attributeValues)
            ->groupBy('attribute_id')
            ->map(fn ($values) => $values->pluck('id')->unique()->values()->all());

        $data['variation_attributes'] = $product->attributes
            ->map(fn ($attribute) => [
                'attribute_id' => $attribute->id,
                'value_ids' => $valuesByAttribute->get($attribute->id, []),
            ])
            ->values()
            ->all();

        $data['variations'] = $product->variations
            ->map(fn ($variation) => [
                'id' => $variation->id,
                'attribute_value_ids' => $variation->attributeValues->pluck('id')->implode(','),
                'sku' => $variation->sku,
                'price' => $variation->price,
                'sale_price' => $variation->sale_price,
                'stock_quantity' => $variation->stock_quantity,
                'weight' => $variation->weight,
                'volume' => $variation->volume,
                'is_active' => $variation->is_active,
                'is_dismissed' => $variation->is_dismissed,
            ])
            ->values()
            ->all();

        return $data;
    }
}

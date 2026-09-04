<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $prices = app(ProductPriceResolver::class)->pricesForProduct($product);

        $regular = $prices['regular_price'];
        $effective = $prices['effective_price'];

        return [
            'id' => (int) $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'type' => $product->type,
            'image' => $product->primaryImage ? (new PublicImageResource($product->primaryImage))->toArray($request) : null,
            'pricing' => self::pricing($prices),
            'availability' => [
                'in_stock' => self::inStock($product),
            ],
            'categories' => $product->categories->map(fn ($category): array => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values()->all(),
            'brand' => $product->brand ? [
                'id' => (int) $product->brand->id,
                'name' => $product->brand->name,
                'slug' => $product->brand->slug,
            ] : null,
            'tags' => $product->tags->map(fn ($tag): array => [
                'id' => (int) $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values()->all(),
        ];
    }

    public static function pricing(array $prices): array
    {
        $regular = $prices['regular_price'];
        $effective = $prices['effective_price'];

        return [
            'regular_price' => $prices['regular_price'] === null ? null : (int) $prices['regular_price'],
            'sale_price' => $prices['sale_price'] === null ? null : (int) $prices['sale_price'],
            'effective_price' => $prices['effective_price'] === null ? null : (int) $prices['effective_price'],
            'minimum_price' => $prices['minimum_price'] === null ? null : (int) $prices['minimum_price'],
            'maximum_price' => $prices['maximum_price'] === null ? null : (int) $prices['maximum_price'],
            'is_discounted' => (bool) $prices['is_discounted'],
            'currency' => 'IRR',
            'discount_percent' => $regular && $effective !== null && $effective < $regular
                ? (int) round((($regular - $effective) * 100) / $regular)
                : 0,
        ];
    }

    private static function inStock(Product $product): bool
    {
        $inventory = app(InventoryService::class);

        if ($product->type === 'variable') {
            return $product->variations->contains(fn ($variation): bool => $inventory->availableQuantity($variation) > 0);
        }

        return $inventory->availableQuantity($product) > 0;
    }
}

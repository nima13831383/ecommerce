<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Services\Catalog\ProductPriceResolver;
use App\Services\Inventory\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductVariationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ProductVariation $variation */
        $variation = $this->resource;
        $prices = app(ProductPriceResolver::class)->pricesForVariation($variation);

        return [
            'id' => (int) $variation->id,
            'sku' => $variation->sku,
            'options' => $variation->relationLoaded('attributeValues')
                ? $variation->attributeValues->map(fn ($value): array => [
                    'attribute_id' => (int) $value->attribute_id,
                    'attribute' => $value->attribute->name,
                    'value_id' => (int) $value->id,
                    'value' => $value->value,
                ])->values()->all()
                : [],
            'pricing' => [
                'regular_price' => (int) $prices['regular_price'],
                'sale_price' => $prices['sale_price'] === null ? null : (int) $prices['sale_price'],
                'effective_price' => (int) $prices['effective_price'],
                'is_discounted' => (bool) $prices['is_discounted'],
                'currency' => 'IRR',
            ],
            'availability' => [
                'in_stock' => app(InventoryService::class)->availableQuantity($variation) > 0,
            ],
            'image' => filled($variation->image)
                ? Storage::disk(ProductImage::storageDisk())->url($variation->image)
                : null,
        ];
    }
}

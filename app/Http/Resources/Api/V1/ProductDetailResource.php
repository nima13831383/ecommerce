<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductDetailResource extends ProductSummaryResource
{
    public function toArray(Request $request): array
    {
        /** @var Product $product */
        $product = $this->resource;
        $data = parent::toArray($request);
        $attributeValues = $product->attributeValues->groupBy('attribute_id');

        return [
            ...$data,
            'short_description' => $product->short_description,
            'description' => $product->description,
            'gallery' => $product->images->map(fn ($image): array => (new PublicImageResource($image))->toArray($request))->values()->all(),
            'attributes' => $product->attributes->map(fn ($attribute): array => [
                'id' => (int) $attribute->id,
                'name' => $attribute->name,
                'slug' => $attribute->slug,
                'options' => $attributeValues->get($attribute->id, collect())->map(fn ($value): array => [
                    'id' => (int) $value->id,
                    'value' => $value->value,
                    'slug' => $value->slug,
                ])->values()->all(),
            ])->values()->all(),
            'variations' => $product->type === 'variable'
                ? $product->variations->map(fn ($variation): array => (new ProductVariationResource($variation))->toArray($request))->values()->all()
                : [],
        ];
    }
}

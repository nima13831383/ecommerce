<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ProductImage $image */
        $image = $this->resource;

        return [
            'url' => Storage::disk(ProductImage::storageDisk())->url($image->path),
            'alt' => $image->alt,
            'sort_order' => (int) $image->sort_order,
        ];
    }
}

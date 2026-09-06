<?php

namespace App\Services\Catalog;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class ProductSlugService
{
    public function generate(string $name, ?int $ignoreProductId = null): string
    {
        $base = $this->normalize($name);

        if ($base === '') {
            throw ValidationException::withMessages(['name' => 'نام محصول الزامی است.']);
        }

        $candidate = $base;
        $suffix = 1;

        while ($this->exists($candidate, $ignoreProductId)) {
            $suffix++;
            $candidate = "{$base}-{$suffix}";
        }

        return $candidate;
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\pL\pN\s_-]+/u', '-', $value) ?? '';
        $value = preg_replace('/[\s_-]+/u', '-', $value) ?? '';

        return trim($value, '-');
    }

    private function exists(string $slug, ?int $ignoreProductId): bool
    {
        return Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreProductId !== null, fn ($query) => $query->whereKeyNot($ignoreProductId))
            ->exists();
    }
}

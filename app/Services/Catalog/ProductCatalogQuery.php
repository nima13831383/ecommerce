<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductCatalogQuery
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()
            ->where('status', 'published')
            ->with([
                'primaryImage',
                'brand',
                'categories',
                'tags',
                'variations' => fn ($variationQuery) => $variationQuery
                    ->where('is_active', true)
                    ->select([
                        'id',
                        'product_id',
                        'price',
                        'sale_price',
                        'sale_starts_at',
                        'sale_ends_at',
                        'stock_quantity',
                        'manage_stock',
                        'is_active',
                    ]),
            ]);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate((int) ($filters['per_page'] ?? 24));
    }

    public function findPublicBySlug(string $slug): ?Product
    {
        return Product::query()
            ->where('status', 'published')
            ->where('slug', $slug)
            ->with([
                'primaryImage',
                'images',
                'brand',
                'categories',
                'tags',
                'attributes',
                'attributeValues.attribute',
                'variations' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with('attributeValues.attribute'),
            ])
            ->first();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        });

        $query->when($filters['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery
            ->where('slug', $slug)
            ->where('is_active', true))
        );

        $query->when($filters['brand'] ?? null, fn (Builder $query, string $slug) => $query->whereIn('brand_id', Brand::query()->where('slug', $slug)->where('is_active', true)->select('id'))
        );

        $query->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type));

        $query->when(($filters['featured'] ?? false) === true, fn (Builder $query) => $query->where('is_featured', true));

        $query->when(array_key_exists('in_stock', $filters), function (Builder $query) use ($filters): void {
            [$expression, $bindings] = $this->inStockExpression();

            if (filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereRaw($expression, $bindings);

                return;
            }

            $query->whereRaw("NOT ({$expression})", $bindings);
        });

        $query->when($filters['min_price'] ?? null, function (Builder $query, int $price): void {
            [$expression, $bindings] = $this->effectivePriceExpression();
            $query->whereRaw("({$expression}) >= ?", [...$bindings, $price]);
        });
        $query->when($filters['max_price'] ?? null, function (Builder $query, int $price): void {
            [$expression, $bindings] = $this->effectivePriceExpression();
            $query->whereRaw("({$expression}) <= ?", [...$bindings, $price]);
        });
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $this->orderByEffectivePrice($query, 'asc'),
            'price_desc' => $this->orderByEffectivePrice($query, 'desc'),
            'name_asc' => $query->orderBy('name')->orderByDesc('id'),
            'name_desc' => $query->orderByDesc('name')->orderByDesc('id'),
            default => $query->latest()->orderByDesc('id'),
        };
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function effectivePriceExpression(): array
    {
        $products = (new Product)->getTable();
        $variations = (new ProductVariation)->getTable();
        $now = now()->toDateTimeString();
        $variationPrice = "CASE WHEN {$variations}.sale_price IS NOT NULL AND ({$variations}.sale_starts_at IS NULL OR {$variations}.sale_starts_at <= ?) AND ({$variations}.sale_ends_at IS NULL OR {$variations}.sale_ends_at >= ?) THEN {$variations}.sale_price ELSE {$variations}.price END";
        $simplePrice = "CASE WHEN {$products}.sale_price IS NOT NULL AND ({$products}.sale_starts_at IS NULL OR {$products}.sale_starts_at <= ?) AND ({$products}.sale_ends_at IS NULL OR {$products}.sale_ends_at >= ?) THEN {$products}.sale_price ELSE {$products}.price END";

        return [
            "CASE WHEN {$products}.type = 'variable' THEN (SELECT MIN({$variationPrice}) FROM {$variations} WHERE {$variations}.product_id = {$products}.id AND {$variations}.is_active = 1) ELSE {$simplePrice} END",
            [$now, $now, $now, $now],
        ];
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function inStockExpression(): array
    {
        $products = (new Product)->getTable();
        $variations = (new ProductVariation)->getTable();
        $reservations = (new InventoryReservation)->getTable();
        $now = now()->toDateTimeString();
        $productReserved = "(SELECT COALESCE(SUM({$reservations}.quantity), 0) FROM {$reservations} WHERE {$reservations}.inventory_owner_type = ? AND {$reservations}.inventory_owner_id = {$products}.id AND {$reservations}.status = 'active' AND {$reservations}.expires_at > ?)";
        $variationReserved = "(SELECT COALESCE(SUM({$reservations}.quantity), 0) FROM {$reservations} WHERE {$reservations}.inventory_owner_type = ? AND {$reservations}.inventory_owner_id = {$variations}.id AND {$reservations}.status = 'active' AND {$reservations}.expires_at > ?)";

        return [
            "(({$products}.type != 'variable' AND ({$products}.stock_quantity - {$productReserved}) > 0) OR ({$products}.type = 'variable' AND EXISTS (SELECT 1 FROM {$variations} WHERE {$variations}.product_id = {$products}.id AND {$variations}.is_active = 1 AND ({$variations}.stock_quantity - {$variationReserved}) > 0)))",
            [Product::class, $now, ProductVariation::class, $now],
        ];
    }

    private function orderByEffectivePrice(Builder $query, string $direction): void
    {
        [$expression, $bindings] = $this->effectivePriceExpression();
        $query->orderByRaw("{$expression} {$direction}", $bindings)->orderByDesc('id');
    }
}

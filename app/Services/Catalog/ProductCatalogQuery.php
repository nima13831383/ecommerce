<?php

namespace App\Services\Catalog;

use App\Enums\CacheRebuildDomain;
use App\Models\Brand;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductCatalogQuery
{
    public function __construct(private readonly StorefrontQueryCache $cache) {}

    public function paginate(array $filters): LengthAwarePaginator
    {
        $filters = $this->normalizeArchiveFilters($filters);
        if ($this->requiresLiveListing($filters)) {
            return $this->paginateUncached($filters);
        }

        return $this->refreshLiveInventory($this->cache->remember(
            CacheRebuildDomain::Products,
            'archive',
            $filters,
            fn (): LengthAwarePaginator => $this->paginateUncached($filters),
        ));
    }

    private function normalizeArchiveFilters(array $filters): array
    {
        $filters = array_filter($filters, static fn (mixed $value): bool => $value !== null && $value !== '');
        $filters['per_page'] = (int) ($filters['per_page'] ?? 24);
        $filters['page'] = (int) ($filters['page'] ?? 1);
        $filters['sort'] = $filters['sort'] ?? 'newest';

        return $filters;
    }

    public function findPublicBySlug(string $slug): ?Product
    {
        return $this->refreshLiveInventory($this->cache->remember(
            CacheRebuildDomain::Products,
            'detail',
            ['slug' => $slug],
            fn (): ?Product => $this->findPublicBySlugUncached($slug),
        ));
    }

    public function paginateUncached(array $filters): LengthAwarePaginator
    {
        $query = $this->publicListingQuery();

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters['sort'] ?? 'newest');

        return $query->paginate(
            (int) ($filters['per_page'] ?? 24),
            ['*'],
            'page',
            (int) ($filters['page'] ?? 1),
        );
    }

    public function findPublicBySlugUncached(string $slug): ?Product
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

    private function publicListingQuery(): Builder
    {
        return Product::query()
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

    }

    private function requiresLiveListing(array $filters): bool
    {
        if (array_key_exists('in_stock', $filters)) {
            return true;
        }

        if (filled($filters['min_price'] ?? null) || filled($filters['max_price'] ?? null)) {
            return true;
        }

        return in_array($filters['sort'] ?? null, ['price_asc', 'price_desc'], true);
    }

    private function refreshLiveInventory(Product|LengthAwarePaginator|null $result): Product|LengthAwarePaginator|null
    {
        $products = $result instanceof LengthAwarePaginator
            ? $result->getCollection()->filter(fn (mixed $product): bool => $product instanceof Product)
            : collect($result === null ? [] : [$result]);

        if ($products->isEmpty()) {
            return $result;
        }

        $states = Product::query()
            ->whereKey($products->pluck('id')->all())
            ->get(['id', 'stock_quantity', 'stock_status', 'manage_stock'])
            ->keyBy('id');

        $products->each(function (Product $product) use ($states): void {
            $state = $states->get($product->id);

            if ($state === null) {
                return;
            }

            $product->setRawAttributes([
                ...$product->getAttributes(),
                'stock_quantity' => $state->stock_quantity,
                'stock_status' => $state->stock_status,
                'manage_stock' => $state->manage_stock,
            ], true);
        });

        $variations = $products
            ->filter(fn (Product $product): bool => $product->relationLoaded('variations'))
            ->flatMap(fn (Product $product): Collection => $product->variations)
            ->keyBy('id');

        if ($variations->isEmpty()) {
            return $result;
        }

        $variationStates = ProductVariation::query()
            ->whereKey($variations->keys())
            ->get(['id', 'stock_quantity', 'stock_status', 'manage_stock'])
            ->keyBy('id');

        $variations->each(function (ProductVariation $variation) use ($variationStates): void {
            $state = $variationStates->get($variation->id);

            if ($state === null) {
                return;
            }

            $variation->setRawAttributes([
                ...$variation->getAttributes(),
                'stock_quantity' => $state->stock_quantity,
                'stock_status' => $state->stock_status,
                'manage_stock' => $state->manage_stock,
            ], true);
        });

        return $result;
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $query->when($filters['search'] ?? null, function (Builder $query, string $search): void {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            });
        });

        $categories = array_values(array_filter((array) ($filters['categories'] ?? ($filters['category'] ?? []))));
        $query->when($categories !== [], function (Builder $query) use ($categories): void {
            $query->whereHas('categories', fn (Builder $categoryQuery) => $categoryQuery
                ->whereIn('slug', $categories)
                ->where('is_active', true));
        });

        $brands = array_values(array_filter((array) ($filters['brands'] ?? ($filters['brand'] ?? []))));
        $query->when($brands !== [], fn (Builder $query) => $query->whereIn('brand_id', Brand::query()->whereIn('slug', $brands)->where('is_active', true)->select('id')));

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

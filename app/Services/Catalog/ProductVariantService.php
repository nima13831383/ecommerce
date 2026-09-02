<?php

namespace App\Services\Catalog;

use App\Enums\InventoryOperation;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Inventory\InventoryService;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function create(Product $product, array $attributes, array $attributeValueIds): ProductVariation
    {
        return DB::transaction(fn () => $this->persist($product, null, $attributes, $attributeValueIds));
    }

    public function update(ProductVariation $variation, array $attributes, array $attributeValueIds): ProductVariation
    {
        return DB::transaction(fn () => $this->persist($variation->product, $variation, $attributes, $attributeValueIds));
    }

    public function synchronize(Product $product, array $attributeRows, array $variationRows): void
    {
        if ($product->type !== 'variable') {
            return;
        }

        DB::transaction(function () use ($product, $attributeRows, $variationRows): void {
            $attributeIds = $this->synchronizeAxes($product, $attributeRows);
            $this->synchronizeAvailableValues($product, $attributeRows);

            $keptVariationIds = [];

            foreach ($variationRows as $row) {
                $attributeValueIds = $this->normalizeIds($row['attribute_value_ids'] ?? []);

                if ($attributeValueIds === []) {
                    continue;
                }

                $variation = $this->variationForRow($product, $row);
                $keptVariationIds[] = $this->persist(
                    $product,
                    $variation,
                    $this->attributesFromRow($row),
                    $attributeValueIds,
                    $attributeIds,
                )->id;
            }

            $this->removeStaleVariations($product, $keptVariationIds);
        });
    }

    public function combinationSignature(Product $product, array $attributeValueIds): string
    {
        return $this->validatedCombination($product, $attributeValueIds)['signature'];
    }

    private function persist(
        Product $product,
        ?ProductVariation $variation,
        array $attributes,
        array $attributeValueIds,
        ?array $configuredAttributeIds = null,
    ): ProductVariation {
        $this->ensureVariableProduct($product);

        $combination = $this->validatedCombination($product, $attributeValueIds, $configuredAttributeIds);
        $this->ensureCombinationIsAvailable($product, $variation, $combination['signature']);

        $variation ??= $product->variations()->make();
        $isNew = ! $variation->exists;
        $requestedStock = array_key_exists('stock_quantity', $attributes) ? max(0, (int) $attributes['stock_quantity']) : null;

        try {
            $variation->fill($this->normalizedAttributes($attributes, $combination['signature']))
                ->product()
                ->associate($product);
            $variation->save();
            $variation->attributeValues()->sync($combination['value_ids']);
            if ($requestedStock !== null) {
                $this->inventory->setOnHand($variation, $requestedStock, $isNew ? InventoryOperation::OpeningStock : InventoryOperation::ManualAdjustment);
            }
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                throw new DomainException('ترکیب ویژگی یا SKU این تنوع قبلاً ثبت شده است.', previous: $exception);
            }

            throw $exception;
        }

        return $variation;
    }

    private function synchronizeAxes(Product $product, array $attributeRows): array
    {
        $attributeIds = [];

        foreach (array_values($attributeRows) as $sortOrder => $row) {
            $attributeId = (int) ($row['attribute_id'] ?? 0);

            if ($attributeId <= 0) {
                throw new DomainException('ویژگی انتخاب‌شده برای تنوع معتبر نیست.');
            }

            if (in_array($attributeId, $attributeIds, true)) {
                throw new DomainException('هر ویژگی فقط یک‌بار می‌تواند محور تنوع باشد.');
            }

            $attributeIds[$attributeId] = ['sort_order' => $sortOrder];
        }

        if ($attributeIds === []) {
            throw new DomainException('محصول متغیر باید حداقل یک ویژگی تنوع داشته باشد.');
        }

        $knownAttributeCount = DB::table('attributes')
            ->whereIn('id', array_keys($attributeIds))
            ->count();

        if ($knownAttributeCount !== count($attributeIds)) {
            throw new DomainException('یکی از ویژگی‌های انتخاب‌شده وجود ندارد.');
        }

        $product->attributes()->sync($attributeIds);

        return array_map('intval', array_keys($attributeIds));
    }

    private function synchronizeAvailableValues(Product $product, array $attributeRows): void
    {
        $valueIds = [];

        foreach ($attributeRows as $row) {
            $attributeId = (int) ($row['attribute_id'] ?? 0);
            $rowValueIds = $this->normalizeIds($row['value_ids'] ?? []);

            if ($rowValueIds === []) {
                throw new DomainException('برای هر ویژگی تنوع باید حداقل یک مقدار انتخاب شود.');
            }

            $matchingValueCount = AttributeValue::query()
                ->where('attribute_id', $attributeId)
                ->whereIn('id', $rowValueIds)
                ->count();

            if ($matchingValueCount !== count($rowValueIds)) {
                throw new DomainException('یکی از مقادیر انتخاب‌شده متعلق به ویژگی موردنظر نیست.');
            }

            $valueIds = [...$valueIds, ...$rowValueIds];
        }

        $product->attributeValues()->sync(array_values(array_unique($valueIds)));
    }

    private function validatedCombination(Product $product, array $attributeValueIds, ?array $configuredAttributeIds = null): array
    {
        $valueIds = $this->normalizeIds($attributeValueIds);

        if ($valueIds === []) {
            throw new DomainException('هر تنوع باید یک ترکیب ویژگی داشته باشد.');
        }

        $configuredAttributeIds ??= $product->attributes()->pluck('attributes.id')->map(fn ($id) => (int) $id)->all();

        if ($configuredAttributeIds === []) {
            throw new DomainException('محصول هیچ محور تنوعی ندارد.');
        }

        $values = AttributeValue::query()
            ->whereIn('id', $valueIds)
            ->get(['id', 'attribute_id']);

        if ($values->count() !== count($valueIds)) {
            throw new DomainException('یکی از مقادیر ویژگی وجود ندارد.');
        }

        $valueIdsByAttribute = $values->groupBy('attribute_id');

        if ($valueIdsByAttribute->count() !== count($valueIds)) {
            throw new DomainException('هر تنوع فقط می‌تواند یک مقدار از هر ویژگی داشته باشد.');
        }

        $actualAttributeIds = $valueIdsByAttribute->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();
        sort($configuredAttributeIds);

        if ($actualAttributeIds !== $configuredAttributeIds) {
            throw new DomainException('ترکیب تنوع باید دقیقاً شامل یک مقدار از هر ویژگی محصول باشد.');
        }

        $availableValueIds = $product->attributeValues()
            ->whereIn('attribute_values.id', $valueIds)
            ->pluck('attribute_values.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($availableValueIds) !== count($valueIds)) {
            throw new DomainException('یکی از مقادیر انتخاب‌شده برای این محصول فعال نشده است.');
        }

        $pairs = $values
            ->mapWithKeys(fn (AttributeValue $value) => [(int) $value->attribute_id => (int) $value->id])
            ->sortKeys();

        return [
            'signature' => $pairs
                ->map(fn (int $valueId, int $attributeId) => "{$attributeId}:{$valueId}")
                ->implode('|'),
            'value_ids' => $pairs->values()->all(),
        ];
    }

    private function ensureVariableProduct(Product $product): void
    {
        if ($product->type !== 'variable') {
            throw new DomainException('تنوع فقط برای محصول متغیر مجاز است.');
        }
    }

    private function ensureCombinationIsAvailable(Product $product, ?ProductVariation $variation, string $signature): void
    {
        $exists = $product->variations()
            ->where('combination_signature', $signature)
            ->when($variation, fn ($query) => $query->whereKeyNot($variation->id))
            ->exists();

        if ($exists) {
            throw new DomainException('این ترکیب ویژگی قبلاً برای محصول ثبت شده است.');
        }
    }

    private function variationForRow(Product $product, array $row): ?ProductVariation
    {
        $variationId = (int) ($row['id'] ?? 0);

        if ($variationId <= 0) {
            return null;
        }

        $variation = $product->variations()->find($variationId);

        if (! $variation) {
            throw new DomainException('تنوع انتخاب‌شده متعلق به این محصول نیست.');
        }

        return $variation;
    }

    private function attributesFromRow(array $row): array
    {
        return Arr::only($row, [
            'sku',
            'price',
            'sale_price',
            'sale_starts_at',
            'sale_ends_at',
            'manage_stock',
            'stock_quantity',
            'stock_status',
            'weight',
            'volume',
            'image',
            'is_active',
            'is_dismissed',
        ]);
    }

    private function normalizedAttributes(array $attributes, string $signature): array
    {
        $price = $this->money($attributes['price'] ?? 0, 'قیمت');
        $salePrice = array_key_exists('sale_price', $attributes) && filled($attributes['sale_price'])
            ? $this->money($attributes['sale_price'], 'قیمت فروش ویژه')
            : null;

        if ($salePrice !== null && $salePrice > $price) {
            throw new DomainException('قیمت فروش ویژه نمی‌تواند بیشتر از قیمت عادی باشد.');
        }

        foreach (['weight', 'volume'] as $field) {
            if (array_key_exists($field, $attributes) && filled($attributes[$field]) && (float) $attributes[$field] <= 0) {
                throw new DomainException('وزن و حجم جایگزین باید بیشتر از صفر باشند.');
            }
        }

        $sku = filled($attributes['sku'] ?? null) ? trim((string) $attributes['sku']) : null;

        return [
            ...$attributes,
            'sku' => $sku,
            'price' => $price,
            'sale_price' => $salePrice,
            'combination_signature' => $signature,
        ];
    }

    private function money(mixed $value, string $label): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
            throw new DomainException("{$label} باید یک مبلغ صحیحِ نامنفی به ریال باشد.");
        }

        return (int) $value;
    }

    private function normalizeIds(array|string|null $ids): array
    {
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);
        $ids = array_map('intval', $ids);
        $ids = array_values(array_filter($ids, fn (int $id) => $id > 0));

        if (count($ids) !== count(array_unique($ids))) {
            throw new DomainException('یک مقدار ویژگی بیش از یک‌بار انتخاب شده است.');
        }

        return $ids;
    }

    private function removeStaleVariations(Product $product, array $keptVariationIds): void
    {
        $staleVariations = $product->variations()
            ->when($keptVariationIds !== [], fn ($query) => $query->whereNotIn('id', $keptVariationIds))
            ->withCount('orderItems')
            ->get();

        foreach ($staleVariations as $variation) {
            if ($variation->order_items_count > 0) {
                $variation->update(['is_active' => false, 'is_dismissed' => true]);

                continue;
            }

            $variation->attributeValues()->detach();
            $variation->delete();
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        return in_array($exception->getCode(), ['23000', '23505'], true);
    }
}

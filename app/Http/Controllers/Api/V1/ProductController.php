<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProductIndexRequest;
use App\Http\Requests\Api\V1\ResolveVariationRequest;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Http\Resources\Api\V1\ProductVariationResource;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Services\Catalog\ProductCatalogQuery;
use App\Services\Catalog\ProductVariantService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCatalogQuery $catalog,
        private readonly ProductVariantService $variants,
    ) {}

    public function index(ProductIndexRequest $request): JsonResponse
    {
        $products = $this->catalog->paginate($request->filters());
        $data = ProductSummaryResource::collection($products)->resolve($request);

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $products->currentPage(),
                'per_page' => $products->perPage(),
                'last_page' => $products->lastPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): ProductDetailResource
    {
        $product = $this->catalog->findPublicBySlug($product->slug);

        abort_unless($product, 404);

        return new ProductDetailResource($product);
    }

    public function resolveVariation(ResolveVariationRequest $request, Product $product): ProductVariationResource|JsonResponse
    {
        if ($product->status !== 'published') {
            abort(404);
        }

        if ($product->type !== 'variable') {
            return $this->error('This product does not have selectable variations.', 'product_not_variable');
        }

        try {
            $rows = $request->validated('options');
            $attributeIds = array_map('intval', array_column($rows, 'attribute_id'));
            $valueIds = array_map('intval', array_column($rows, 'value_id'));

            if (count($attributeIds) !== count(array_unique($attributeIds))) {
                return $this->error('Each attribute may be selected only once.', 'duplicate_attribute');
            }

            $configuredAttributes = $product->attributes()->pluck('attributes.id')->map(fn ($id): int => (int) $id)->all();
            $values = AttributeValue::query()->with('attribute')->whereIn('id', $valueIds)->get();

            if ($values->count() !== count($valueIds)) {
                return $this->error('One or more selected values do not exist.', 'invalid_value');
            }

            foreach ($rows as $row) {
                $attributeId = (int) $row['attribute_id'];
                $value = $values->firstWhere('id', (int) $row['value_id']);

                if (! in_array($attributeId, $configuredAttributes, true)) {
                    return $this->error('The selected attribute is not available for this product.', 'invalid_attribute');
                }

                if ((int) $value->attribute_id !== $attributeId) {
                    return $this->error('The selected value does not belong to the selected attribute.', 'invalid_value');
                }
            }

            $signature = $this->variants->combinationSignature($product, $valueIds);
            $variation = $product->variations()
                ->where('combination_signature', $signature)
                ->where('is_active', true)
                ->with('attributeValues.attribute')
                ->first();

            if (! $variation) {
                return $this->error('No active variation matches the selected options.', 'variation_unavailable');
            }

            return new ProductVariationResource($variation);
        } catch (DomainException) {
            return $this->error('The selected variation is invalid.', 'variation_invalid');
        } catch (ValidationException $exception) {
            return $this->error('The selected variation is invalid.', 'variation_invalid', 422, $exception->errors());
        }
    }

    private function error(string $message, string $code, int $status = 422, array $errors = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'errors' => $errors,
            'code' => $code,
        ], $status);
    }
}

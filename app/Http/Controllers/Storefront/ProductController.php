<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\ProductIndexRequest;
use App\Http\Resources\Api\V1\ProductDetailResource;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductCatalogQuery $catalog) {}

    public function index(ProductIndexRequest $request): View|JsonResponse
    {
        $paginator = $this->catalog->paginate(array_merge(
            ['per_page' => 24],
            $request->filters(),
        ));
        $paginator->withQueryString();

        if ($request->boolean('ajax')) {
            return response()->json([
                'html' => view('storefront.products._results', [
                    'products' => ProductSummaryResource::collection($paginator)->resolve($request),
                    'paginator' => $paginator,
                ])->render(),
                'count' => $paginator->total(),
            ]);
        }

        return view('storefront.products.index', [
            'products' => ProductSummaryResource::collection($paginator)->resolve($request),
            'paginator' => $paginator,
            'filters' => $request->validated(),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'brands' => Brand::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['name', 'slug']),
            'title' => 'محصولات | لوکسیر',
        ]);
    }

    public function show(Request $request, Product $product): View
    {
        $product = $this->catalog->findPublicBySlug($product->slug);
        abort_unless($product, 404);

        $detail = (new ProductDetailResource($product))->resolve($request);
        $related = Product::query()->where('status', 'published')->where('id', '<>', $product->id)
            ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $product->categories->pluck('id')))
            ->with(['primaryImage', 'brand', 'categories', 'tags', 'variations' => fn ($query) => $query->where('is_active', true)])
            ->latest()->limit(4)->get()
            ->map(fn (Product $item): array => (new ProductSummaryResource($item))->resolve($request));

        return view('storefront.products.show', [
            'product' => $detail,
            'relatedProducts' => $related,
            'title' => $detail['name'].' | لوکسیر',
        ]);
    }
}

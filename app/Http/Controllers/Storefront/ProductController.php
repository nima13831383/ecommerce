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
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(private readonly ProductCatalogQuery $catalog) {}

    public function index(ProductIndexRequest $request): View
    {
        $paginator = $this->catalog->paginate(array_merge(
            ['per_page' => 24],
            $request->filters(),
        ));
        $paginator->withQueryString();

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

        return view('storefront.products.show', [
            'product' => $detail,
            'title' => $detail['name'].' | لوکسیر',
        ]);
    }
}

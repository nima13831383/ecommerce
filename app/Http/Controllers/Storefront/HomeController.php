<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductSummaryResource;
use App\Models\Category;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __construct(private readonly ProductCatalogQuery $catalog) {}

    public function __invoke(Request $request): View
    {
        $featured = $this->catalog->paginate([
            'featured' => true,
            'sort' => 'newest',
            'per_page' => 7,
        ]);

        return view('storefront.home', [
            'title' => 'لوکسیر | فروشگاه زیبایی',
            'featuredProducts' => ProductSummaryResource::collection($featured)->resolve($request),
            'categories' => Category::query()->where('is_active', true)->where('is_hidden', false)->orderByDesc('is_featured')->orderBy('sort_order')->orderBy('name')->limit(8)->get(['name', 'slug', 'image', 'icon']),
        ]);
    }
}

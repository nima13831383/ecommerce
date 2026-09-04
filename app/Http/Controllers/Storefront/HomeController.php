<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductSummaryResource;
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
        ]);
    }
}

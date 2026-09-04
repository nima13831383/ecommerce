<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontOrderPresenter;
use App\Services\Storefront\StorefrontOrderQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function __construct(
        private readonly StorefrontOrderQuery $orders,
        private readonly StorefrontOrderPresenter $orderPresenter,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $recentOrders = $this->orders->recentFor($user)
            ->map(fn ($order): array => $this->orderPresenter->summary($order))
            ->all();

        return view('storefront.account.index', [
            'user' => $user,
            'addressCount' => $user->addresses()->count(),
            'orderCount' => $user->orders()->count(),
            'recentOrders' => $recentOrders,
            'title' => 'حساب کاربری | لوکسیر',
        ]);
    }

    public function profile(Request $request): View
    {
        return view('storefront.account.profile', [
            'user' => $request->user(),
            'title' => 'اطلاعات حساب | لوکسیر',
        ]);
    }
}

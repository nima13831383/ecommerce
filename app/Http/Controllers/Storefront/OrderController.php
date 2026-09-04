<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Storefront\StorefrontOrderPresenter;
use App\Services\Storefront\StorefrontOrderQuery;
use App\Services\Storefront\StorefrontPaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(
        private readonly StorefrontOrderQuery $orders,
        private readonly StorefrontOrderPresenter $presenter,
        private readonly StorefrontPaymentGateway $paymentGateway,
    ) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, ['pending', 'awaiting_payment', 'processing', 'shipped', 'delivered', 'completed', 'cancelled', 'refunded', 'failed'], true)
            ? $status
            : null;
        $orders = $this->orders->paginateFor($request->user(), $status);
        $orders->setCollection($orders->getCollection()->map(fn ($order): array => $this->presenter->summary($order)));

        return view('storefront.account.orders', [
            'user' => $request->user(),
            'orders' => $orders,
            'status' => $status,
            'title' => 'سفارش‌های من | لوکسیر',
        ]);
    }

    public function show(Request $request, string $order): View
    {
        $model = $this->orders->findFor($request->user(), $order);
        $detail = $this->presenter->detail($model);

        return view('storefront.account.order-detail', [
            'user' => $request->user(),
            'order' => $detail,
            'paymentRetryAllowed' => $this->presenter->paymentRetryAllowed($model, $this->paymentGateway->alias() !== null),
            'title' => 'جزئیات سفارش | لوکسیر',
        ]);
    }
}

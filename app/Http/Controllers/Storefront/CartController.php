<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\AddressValidationException;
use App\Exceptions\CouponValidationException;
use App\Exceptions\ShippingConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CartCouponRequest;
use App\Http\Requests\Storefront\CartItemStoreRequest;
use App\Http\Requests\Storefront\CartItemUpdateRequest;
use App\Http\Requests\Storefront\ShippingQuoteRequest;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingOptionCatalog;
use App\Services\Storefront\StorefrontCartContext;
use App\Services\Storefront\StorefrontShippingQuoteService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $carts,
        private readonly StorefrontCartContext $context,
        private readonly StorefrontShippingQuoteService $shippingQuotes,
        private readonly ShippingOptionCatalog $shippingOptions,
    ) {}

    public function index(): View
    {
        $cart = $this->context->current(create: true);
        $result = $this->carts->recalculate($cart);

        $selection = session('storefront_shipping_selection');
        $shipping = null;
        $shippingError = null;
        if (auth()->check() && is_array($selection) && $result->cart->items->isNotEmpty()) {
            try {
                $quote = $this->shippingQuotes->quote(auth()->user(), $result->cart, (int) ($selection['address_id'] ?? 0), (string) ($selection['service'] ?? ''), (string) ($selection['payment_type'] ?? ''));
                $shipping = $this->shippingQuotes->present($quote, (string) ($selection['payment_type'] ?? ''));
            } catch (AddressValidationException|ShippingConfigurationException|DomainException) {
                $shippingError = 'امکان محاسبه هزینه ارسال با انتخاب فعلی وجود ندارد.';
            }
        }
        $cartView = $this->context->present($result->cart, $result->issues);
        $cartView['shipping_total'] = $shipping['amount'] ?? 0;
        $cartView['grand_total_with_shipping'] = $cartView['grand_total'] + $cartView['shipping_total'];

        return view('storefront.cart.index', [
            'cartView' => $cartView,
            'shipping' => $shipping,
            'shippingError' => $shippingError,
            'shippingSelection' => is_array($selection) ? $selection : [],
            'addresses' => auth()->check() ? auth()->user()->addresses()->latest()->get() : collect(),
            'shippingServices' => $this->shippingOptions->services(),
            'shippingPaymentTypes' => $this->shippingOptions->paymentTypes(),
            'title' => 'سبد خرید | لوکسیر',
        ]);
    }

    public function applyCoupon(CartCouponRequest $request): RedirectResponse
    {
        try {
            $this->carts->applyCoupon($this->context->current(create: true), (string) $request->input('coupon'), $request->user()?->id);

            return redirect()->route('storefront.cart.show')->with('status', 'کد تخفیف با موفقیت اعمال شد.');
        } catch (CouponValidationException|DomainException) {
            return redirect()->route('storefront.cart.show')->withErrors(['coupon' => 'کد تخفیف معتبر نیست یا برای این سبد قابل استفاده نیست.']);
        }
    }

    public function removeCoupon(): RedirectResponse
    {
        try {
            $this->carts->removeCoupon($this->context->current(create: true));

            return redirect()->route('storefront.cart.show')->with('status', 'کد تخفیف حذف شد.');
        } catch (DomainException) {
            return redirect()->route('storefront.cart.show')->withErrors(['coupon' => 'حذف کد تخفیف امکان‌پذیر نیست.']);
        }
    }

    public function quoteShipping(ShippingQuoteRequest $request): RedirectResponse
    {
        try {
            $cart = $this->context->current(create: true);
            $recalculated = $this->carts->recalculate($cart, $request->user()->id);
            if ($recalculated->hasIssues()) {
                return redirect()->route('storefront.cart.show')->withErrors(['shipping' => 'ابتدا وضعیت کالاهای سبد را بررسی کنید.']);
            }

            $addressId = $request->integer('address_id');
            $service = (string) $request->input('service');
            $paymentType = (string) $request->input('payment_type');
            $quote = $this->shippingQuotes->quote($request->user(), $recalculated->cart, $addressId, $service, $paymentType);
            if ($quote->available === false) {
                return redirect()->route('storefront.cart.show')->withErrors(['shipping' => 'روش ارسال انتخاب‌شده در دسترس نیست.']);
            }

            session()->put('storefront_shipping_selection', [
                'address_id' => $addressId,
                'service' => $service,
                'payment_type' => $paymentType,
            ]);

            return redirect()->route('storefront.cart.show')->with('status', 'هزینه ارسال با موفقیت محاسبه شد.');
        } catch (AddressValidationException|ShippingConfigurationException|DomainException) {
            return redirect()->route('storefront.cart.show')->withErrors(['shipping' => 'امکان محاسبه هزینه ارسال با اطلاعات فعلی وجود ندارد.']);
        }
    }

    public function store(CartItemStoreRequest $request): RedirectResponse
    {
        try {
            $product = Product::query()->withTrashed()->findOrFail((int) $request->integer('product_id'));
            $variationId = $request->integer('variation_id');
            $variation = $variationId > 0 ? ProductVariation::query()->find($variationId) : null;

            if ($variationId > 0 && ! $variation) {
                throw new DomainException('variation_missing');
            }

            $this->carts->addItem(
                $this->context->current(create: true),
                $product,
                $request->integer('quantity'),
                $variation,
            );

            return redirect()
                ->route('storefront.cart.show')
                ->with('status', 'محصول با موفقیت به سبد خرید اضافه شد.');
        } catch (DomainException|ModelNotFoundException) {
            return $this->cartError('امکان افزودن این محصول به سبد خرید وجود ندارد.');
        }
    }

    public function update(CartItemUpdateRequest $request, CartItem $item): RedirectResponse
    {
        try {
            $cart = $this->context->current(create: true);
            $ownedItem = $cart->items()->whereKey($item->id)->firstOrFail();
            $this->carts->updateQuantity($cart, $ownedItem, $request->integer('quantity'));

            return redirect()
                ->route('storefront.cart.show')
                ->with('status', 'تعداد محصول به‌روز شد.');
        } catch (DomainException|ModelNotFoundException) {
            return $this->cartError('امکان به‌روزرسانی این محصول وجود ندارد.');
        }
    }

    public function remove(CartItem $item): RedirectResponse
    {
        try {
            $cart = $this->context->current(create: true);
            $ownedItem = $cart->items()->whereKey($item->id)->firstOrFail();
            $this->carts->removeItem($cart, $ownedItem);

            return redirect()
                ->route('storefront.cart.show')
                ->with('status', 'محصول از سبد خرید حذف شد.');
        } catch (DomainException|ModelNotFoundException) {
            return $this->cartError('این محصول در سبد خرید فعلی وجود ندارد.');
        }
    }

    public function clear(): RedirectResponse
    {
        try {
            $this->carts->clear($this->context->current(create: true));

            return redirect()
                ->route('storefront.cart.show')
                ->with('status', 'سبد خرید خالی شد.');
        } catch (DomainException|ModelNotFoundException) {
            return $this->cartError('امکان خالی کردن سبد خرید وجود ندارد.');
        }
    }

    private function cartError(string $message): RedirectResponse
    {
        return redirect()
            ->route('storefront.cart.show')
            ->withErrors(['cart' => $message]);
    }
}

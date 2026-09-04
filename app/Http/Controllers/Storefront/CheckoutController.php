<?php

namespace App\Http\Controllers\Storefront;

use App\Exceptions\AddressValidationException;
use App\Exceptions\CheckoutValidationException;
use App\Exceptions\ShippingConfigurationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CheckoutRequest;
use App\Models\Cart;
use App\Models\Order;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutInput;
use App\Services\Checkout\CheckoutService;
use App\Services\Shipping\ShippingOptionCatalog;
use App\Services\Storefront\StorefrontCartContext;
use App\Services\Storefront\StorefrontPaymentGateway;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly CartService $carts,
        private readonly StorefrontCartContext $cartContext,
        private readonly ShippingOptionCatalog $shippingOptions,
        private readonly StorefrontPaymentGateway $paymentGateway,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $cart = $this->cartContext->current();
        if (! $cart || $cart->items()->doesntExist()) {
            return redirect()->route('storefront.cart.show')->withErrors([
                'checkout' => 'برای ادامه، ابتدا محصولی به سبد خرید اضافه کنید.',
            ]);
        }

        $addresses = $request->user()->addresses()->latest()->get();
        $selection = session('storefront_shipping_selection');
        $shippingAddressId = is_array($selection) && isset($selection['address_id'])
            ? (int) $selection['address_id']
            : (int) ($addresses->firstWhere('is_default', true)?->id ?? $addresses->first()?->id ?? 0);
        $shippingService = is_array($selection) && isset($selection['service'])
            ? (string) $selection['service']
            : 'pishtaz';
        $paymentType = is_array($selection) && isset($selection['payment_type'])
            ? (string) $selection['payment_type']
            : 'online';
        $idempotencyKey = $this->idempotencyKey($request, (int) $cart->id);
        $preview = null;
        $previewError = null;

        if ($shippingAddressId > 0) {
            try {
                $preview = $this->checkout->preview($request->user(), $this->input(
                    $cart->id,
                    $shippingAddressId,
                    null,
                    $shippingService,
                    $paymentType,
                    $idempotencyKey,
                ));
            } catch (ShippingConfigurationException) {
                $previewError = 'محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.';
            } catch (AddressValidationException) {
                $previewError = 'لطفاً آدرس ارسال را انتخاب کنید.';
            } catch (CheckoutValidationException|DomainException) {
                $previewError = 'اطلاعات ارسال کامل نیست. لطفاً روش ارسال را دوباره انتخاب کنید.';
            }
        }

        return view('storefront.checkout.index', [
            'cartView' => $this->cartContext->present($preview?->cart ?? $cart),
            'preview' => $preview,
            'previewError' => $previewError,
            'addresses' => $addresses,
            'selectedAddressId' => $shippingAddressId,
            'shippingServices' => $this->shippingOptions->services(),
            'shippingPaymentTypes' => $this->shippingOptions->paymentTypes(),
            'selectedService' => $shippingService,
            'selectedPaymentType' => $paymentType,
            'idempotencyKey' => $idempotencyKey,
            'paymentAvailable' => $this->paymentGateway->alias() !== null,
            'title' => 'تسویه حساب | لوکسیر',
        ]);
    }

    public function store(CheckoutRequest $request): RedirectResponse
    {
        $cart = $this->cartForSubmission($request);
        if (! $cart) {
            return redirect()->route('storefront.cart.show')->withErrors([
                'checkout' => 'سبد خرید فعالی برای ثبت سفارش وجود ندارد.',
            ]);
        }

        try {
            $result = $this->checkout->placeOrder(
                $request->user(),
                $this->input(
                    $cart->id,
                    $request->integer('shipping_address_id'),
                    $request->filled('billing_address_id') ? $request->integer('billing_address_id') : null,
                    (string) $request->string('shipping_service'),
                    (string) $request->string('shipping_payment_type'),
                    (string) $request->string('idempotency_key'),
                    $request->input('customer_note'),
                    $request->ip(),
                    $request->userAgent(),
                ),
                $request->user()->id,
            );
        } catch (ShippingConfigurationException) {
            return back()->withInput()->withErrors([
                'checkout' => 'محاسبه هزینه ارسال در حال حاضر امکان‌پذیر نیست.',
            ]);
        } catch (AddressValidationException) {
            return back()->withInput()->withErrors([
                'checkout' => 'لطفاً آدرس ارسال را انتخاب کنید.',
            ]);
        } catch (CheckoutValidationException|DomainException) {
            return back()->withInput()->withErrors([
                'checkout' => 'ثبت سفارش انجام نشد؛ اطلاعات سبد خرید، آدرس و روش ارسال را بررسی کنید.',
            ]);
        }

        session()->put('storefront_checkout_idempotency', [
            'cart_id' => $cart->id,
            'key' => (string) $request->string('idempotency_key'),
        ]);
        session()->forget('storefront_shipping_selection');

        return redirect()->route('storefront.checkout.success', ['order' => $result->order->id])
            ->with('status', 'سفارش شما با موفقیت ثبت شد.');
    }

    public function success(Request $request, int $order): View
    {
        $model = Order::query()
            ->whereBelongsTo($request->user())
            ->whereKey($order)
            ->firstOrFail();

        return view('storefront.checkout.success', [
            'order' => $model->load('items'),
            'paymentAvailable' => $this->paymentGateway->alias() !== null,
            'title' => 'سفارش ثبت شد | لوکسیر',
        ]);
    }

    private function idempotencyKey(Request $request, int $cartId): string
    {
        $stored = session('storefront_checkout_idempotency');
        if (is_array($stored) && (int) ($stored['cart_id'] ?? 0) === $cartId && is_string($stored['key'] ?? null)) {
            return $stored['key'];
        }

        $key = (string) Str::uuid();
        session()->put('storefront_checkout_idempotency', ['cart_id' => $cartId, 'key' => $key]);

        return $key;
    }

    private function input(
        int $cartId,
        int $shippingAddressId,
        ?int $billingAddressId,
        string $shippingService,
        string $paymentType,
        ?string $idempotencyKey,
        ?string $customerNote = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): CheckoutInput {
        return new CheckoutInput(
            cartId: $cartId,
            shippingAddressId: $shippingAddressId,
            billingAddressId: $billingAddressId,
            shippingService: $shippingService,
            shippingPaymentType: $paymentType,
            idempotencyKey: $idempotencyKey,
            customerNote: $customerNote,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
        );
    }

    private function cartForSubmission(CheckoutRequest $request): ?Cart
    {
        $cart = $this->cartContext->current();
        if ($cart) {
            return $cart;
        }

        $stored = session('storefront_checkout_idempotency');
        if (! is_array($stored) || (int) ($stored['cart_id'] ?? 0) < 1) {
            return null;
        }

        try {
            return $this->carts->getForUser($request->user(), (int) $stored['cart_id']);
        } catch (DomainException) {
            return null;
        }
    }
}

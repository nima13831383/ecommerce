<?php

namespace App\Services\Checkout;

use App\Enums\AddressType;
use App\Exceptions\CheckoutValidationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\ProductVariation;
use App\Models\User;
use App\Services\Addresses\AddressService;
use App\Services\Cart\CartService;
use App\Services\CouponService;
use App\Services\Orders\OrderCreationContext;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\OrderService;
use App\Services\Shipping\Data\WordpressShippingDataLoader;
use App\Services\Shipping\DTO\ShippingQuoteInput;
use App\Services\Shipping\DTO\ShippingQuoteResult;
use App\Services\Shipping\PostShippingCalculator;
use App\Services\Shipping\ShippingOptionCatalog;
use Illuminate\Support\Facades\DB;

class CheckoutService
{
    public function __construct(
        private readonly CartService $carts,
        private readonly AddressService $addresses,
        private readonly PostShippingCalculator $shipping,
        private readonly ShippingOptionCatalog $shippingOptions,
        private readonly WordpressShippingDataLoader $geography,
        private readonly CouponService $coupons,
        private readonly OrderService $orders,
    ) {}

    public function preview(User $user, CheckoutInput $input): CheckoutResult
    {
        $cart = $this->carts->getForUser($user, $input->cartId);
        $prepared = $this->prepare($user, $cart, $input);

        return $prepared['result'];
    }

    public function placeOrder(User $user, CheckoutInput $input, ?int $actorId = null): CheckoutResult
    {
        $cart = $this->carts->getForUser($user, $input->cartId);

        if ($input->idempotencyKey === null || trim($input->idempotencyKey) === '') {
            throw new CheckoutValidationException('An idempotency key is required to place an order.');
        }

        $fingerprint = $this->fingerprint($user, $cart, $input);
        $existing = $this->orders->findIdempotent($input->idempotencyKey, $fingerprint);
        if ($existing) {
            return $this->resultFromOrder($cart, $existing);
        }

        return DB::transaction(function () use ($user, $cart, $input, $actorId, $fingerprint): CheckoutResult {
            $cart = $this->carts->getForUser($user, $cart->id);
            $prepared = $this->prepare($user, $cart, $input);
            /** @var Cart $preparedCart */
            $preparedCart = $prepared['cart'];
            /** @var array<string, mixed> $details */
            $details = $prepared['details'];
            /** @var OrderPricing $pricing */
            $pricing = $prepared['pricing'];

            $order = $this->orders->create(
                $this->lines($preparedCart),
                $details,
                $actorId,
                new OrderCreationContext($pricing, trim($input->idempotencyKey), $fingerprint),
            );

            if (! $order->wasRecentlyCreated) {
                return $this->resultFromOrder($preparedCart, $order);
            }

            if ($pricing->couponId !== null) {
                $coupon = Coupon::query()->findOrFail($pricing->couponId);
                $this->coupons->apply($coupon, $preparedCart, $order, $user->id);
            }

            $converted = $this->carts->convert($preparedCart);

            return new CheckoutResult(
                cart: $converted,
                order: $order,
                shippingQuote: $prepared['quote'],
                subtotal: $order->items_subtotal,
                discountTotal: $order->discount_total,
                taxTotal: $order->tax_total,
                shippingTotal: $order->shipping_total,
                grandTotal: $order->grand_total,
            );
        });
    }

    /** @return array{result: CheckoutResult, cart: Cart, details: array<string, mixed>, pricing: OrderPricing, quote: ShippingQuoteResult} */
    private function prepare(User $user, Cart $cart, CheckoutInput $input): array
    {
        $this->assertShippingOptions($input);
        $this->assertOrigin($input);

        $recalculated = $this->carts->recalculate($cart, $user->id);
        if ($recalculated->hasIssues()) {
            throw new CheckoutValidationException(implode(' ', $recalculated->issues));
        }

        $cart = $recalculated->cart;
        if ($cart->status !== 'active') {
            throw new CheckoutValidationException('Only an active cart can be checked out.');
        }

        $shippingAddress = $this->addresses->getForUser($user, $input->shippingAddressId);
        $billingAddress = $input->billingAddressId === null
            ? $shippingAddress
            : $this->addresses->getForUser($user, $input->billingAddressId);
        $this->assertAddressType($shippingAddress->type, [AddressType::Shipping, AddressType::Both], 'shipping');
        $this->assertAddressType($billingAddress->type, [AddressType::Billing, AddressType::Both], 'billing');
        $shippingAddressSnapshot = $this->addresses->snapshot($shippingAddress);
        $billingSnapshot = $this->addresses->snapshot($billingAddress);

        if ($shippingAddressSnapshot['province_id'] === null || $shippingAddressSnapshot['city_id'] === null) {
            throw new CheckoutValidationException('The shipping address must contain a valid province and city.');
        }

        $quoteInput = new ShippingQuoteInput(
            originProvinceId: $input->originProvinceId,
            originCityId: $input->originCityId,
            destinationProvinceId: (int) $shippingAddressSnapshot['province_id'],
            destinationCityId: (int) $shippingAddressSnapshot['city_id'],
            weightGrams: $this->weightGrams($cart),
            declaredValueRials: (int) $cart->subtotal,
            parcelType: $input->parcelType,
            paymentType: $input->shippingPaymentType,
            packageSizeId: $input->packageSizeId,
            service: $input->shippingService,
        );
        $quote = $this->shipping->calculate($quoteInput);
        if ($quote->available === false) {
            throw new CheckoutValidationException('The selected shipping service is unavailable for this destination.');
        }

        $couponSnapshot = null;
        $couponId = null;
        if ($cart->coupon_id !== null) {
            $coupon = Coupon::query()->find($cart->coupon_id);
            if (! $coupon) {
                throw new CheckoutValidationException('The selected coupon is no longer available.');
            }

            $evaluation = $this->coupons->evaluateCoupon($coupon, $cart, $user->id);
            $couponId = $coupon->id;
            $couponSnapshot = [
                'id' => $coupon->id,
                'code' => $coupon->code,
                'type' => $coupon->type,
                'amount' => (int) $coupon->amount,
                'eligible_amount' => $evaluation->eligibleAmount,
                'discount_amount' => $evaluation->discountAmount,
            ];
        }

        $shippingSnapshot = [
            'service' => $quote->service,
            'cost' => $quote->total,
            'currency' => $quote->currency,
            'parcel_type' => $input->parcelType,
            'payment_type' => $input->shippingPaymentType,
            'package_size_id' => $input->packageSizeId,
            'weight_grams' => $quoteInput->weightGrams,
            'declared_value_rials' => $quoteInput->declaredValueRials,
            'breakdown' => $quote->breakdown,
            'metadata' => $quote->metadata,
        ];
        $pricing = new OrderPricing(
            discountTotal: (int) $cart->discount_total,
            shippingTotal: $quote->total,
            couponId: $couponId,
            couponSnapshot: $couponSnapshot,
            shippingSnapshot: $shippingSnapshot,
        );

        $details = [
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'customer_name' => trim($shippingAddress->first_name.' '.$shippingAddress->last_name),
            'customer_mobile' => $shippingAddress->mobile,
            'customer_email' => $user->email,
            'billing_address' => $billingSnapshot,
            'shipping_address' => $shippingAddressSnapshot,
            'customer_note' => $input->customerNote,
            'ip_address' => $input->ipAddress,
            'user_agent' => $input->userAgent,
            'reservation_expires_at' => $input->reservationExpiresAt ?? now()->addMinutes(30),
        ];

        $grandTotal = (int) $cart->grand_total + $quote->total;
        if ($grandTotal < 0) {
            throw new CheckoutValidationException('Checkout total is invalid.');
        }

        $result = new CheckoutResult(
            cart: $cart,
            order: null,
            shippingQuote: $quote,
            subtotal: (int) $cart->subtotal,
            discountTotal: (int) $cart->discount_total,
            taxTotal: (int) $cart->tax_total,
            shippingTotal: $quote->total,
            grandTotal: $grandTotal,
        );

        return compact('result', 'cart', 'details', 'pricing', 'quote');
    }

    /** @return array<int, array{product_id: int, quantity: int, product_variation_id?: int}> */
    private function lines(Cart $cart): array
    {
        return $cart->items->map(fn (CartItem $item): array => array_filter([
            'product_id' => (int) $item->product_id,
            'product_variation_id' => $item->product_variation_id === null ? null : (int) $item->product_variation_id,
            'quantity' => (int) $item->quantity,
        ], fn (mixed $value): bool => $value !== null))->values()->all();
    }

    private function weightGrams(Cart $cart): float
    {
        $total = 0.0;
        foreach ($cart->items as $item) {
            $weightKg = $item->variation instanceof ProductVariation && $item->variation->weight !== null
                ? (float) $item->variation->weight
                : (float) ($item->product?->weight ?? 0);
            if ($weightKg <= 0) {
                throw new CheckoutValidationException('Every shippable cart item must have a positive weight.');
            }
            $total += $weightKg * 1000 * (int) $item->quantity;
        }

        return $total;
    }

    private function assertShippingOptions(CheckoutInput $input): void
    {
        if (! array_key_exists($input->shippingService, $this->shippingOptions->services())
            || ! array_key_exists($input->parcelType, $this->shippingOptions->parcelTypes())
            || ! array_key_exists($input->shippingPaymentType, $this->shippingOptions->paymentTypes())
            || ! array_key_exists($input->packageSizeId, $this->shippingOptions->packageSizes())) {
            throw new CheckoutValidationException('One or more shipping options are invalid.');
        }
    }

    private function assertOrigin(CheckoutInput $input): void
    {
        if ($this->geography->provinceName($input->originProvinceId) === null
            || $this->geography->cityName($input->originCityId, $input->originProvinceId) === null) {
            throw new CheckoutValidationException('The shipping origin province and city are invalid.');
        }
    }

    /** @param AddressType|string|null $type @param array<int, AddressType> $allowed */
    private function assertAddressType(AddressType|string|null $type, array $allowed, string $purpose): void
    {
        $type = $type instanceof AddressType ? $type : AddressType::tryFrom((string) $type);
        if ($type === null || ! in_array($type, $allowed, true)) {
            throw new CheckoutValidationException("The selected address cannot be used as a {$purpose} address.");
        }
    }

    private function fingerprint(User $user, Cart $cart, CheckoutInput $input): string
    {
        $lines = $cart->items()->orderBy('id')->get(['product_id', 'product_variation_id', 'quantity'])->map(fn (CartItem $item): array => [
            'product_id' => (int) $item->product_id,
            'product_variation_id' => $item->product_variation_id === null ? null : (int) $item->product_variation_id,
            'quantity' => (int) $item->quantity,
        ])->all();

        return hash('sha256', json_encode([
            'user_id' => $user->id,
            'cart_id' => $cart->id,
            'shipping_address_id' => $input->shippingAddressId,
            'billing_address_id' => $input->billingAddressId,
            'origin_province_id' => $input->originProvinceId,
            'origin_city_id' => $input->originCityId,
            'shipping_service' => $input->shippingService,
            'parcel_type' => $input->parcelType,
            'payment_type' => $input->shippingPaymentType,
            'package_size_id' => $input->packageSizeId,
            'lines' => $lines,
        ], JSON_THROW_ON_ERROR));
    }

    private function resultFromOrder(Cart $cart, Order $order): CheckoutResult
    {
        return new CheckoutResult(
            cart: $cart->fresh(['items']),
            order: $order,
            shippingQuote: null,
            subtotal: (int) $order->items_subtotal,
            discountTotal: (int) $order->discount_total,
            taxTotal: (int) $order->tax_total,
            shippingTotal: (int) $order->shipping_total,
            grandTotal: (int) $order->grand_total,
        );
    }
}

<?php

namespace App\Services\Storefront;

use App\Http\Resources\Api\V1\PublicImageResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Auth;

class StorefrontCartContext
{
    public const SESSION_KEY = 'storefront_cart_token';

    public function __construct(
        private readonly CartService $carts,
        private readonly InventoryService $inventory,
    ) {}

    public function current(bool $create = false): ?Cart
    {
        if (Auth::check()) {
            if ($create) {
                return $this->carts->getOrCreateForUser((int) Auth::id());
            }

            return Cart::query()
                ->where('user_id', Auth::id())
                ->where('status', 'active')
                ->first();
        }

        $token = session()->get(self::SESSION_KEY);

        if (! is_string($token) || $token === '') {
            return $create ? $this->createGuestCart() : null;
        }

        $cart = Cart::query()
            ->where('token', $token)
            ->where('status', 'active')
            ->first();

        if ($cart || ! $create) {
            return $cart;
        }

        session()->forget(self::SESSION_KEY);

        return $this->createGuestCart();
    }

    public function present(?Cart $cart, array $issues = []): array
    {
        if (! $cart) {
            return [
                'lines' => [],
                'item_count' => 0,
                'line_count' => 0,
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'shipping_total' => 0,
                'grand_total' => 0,
                'issues' => $issues,
            ];
        }

        $cart->load([
            'items.product' => fn ($query) => $query->withTrashed()->with('primaryImage'),
            'items.variation.attributeValues.attribute',
            'coupon',
        ]);

        $lines = $cart->items->map(function (CartItem $item): array {
            $available = $this->lineIsAvailable($item);

            return [
                'id' => (int) $item->id,
                'product_id' => (int) $item->product_id,
                'variation_id' => $item->product_variation_id === null ? null : (int) $item->product_variation_id,
                'name' => $item->product?->name ?? 'محصول در دسترس نیست',
                'url' => $item->product?->trashed() ? null : ($item->product ? route('storefront.products.show', ['product' => $item->product->slug]) : null),
                'image' => $item->product?->primaryImage
                    ? (new PublicImageResource($item->product->primaryImage))->toArray(request())
                    : null,
                'options' => $item->variation?->attributeValues
                    ? $item->variation->attributeValues->map(fn ($value): array => [
                        'attribute' => $value->attribute->name,
                        'value' => $value->value,
                    ])->values()->all()
                    : [],
                'quantity' => (int) $item->quantity,
                'unit_price' => $available ? (int) $item->unit_price : 0,
                'line_total' => $available ? (int) $item->line_total : 0,
                'available' => $available,
            ];
        })->values()->all();

        return [
            'lines' => $lines,
            'item_count' => (int) $cart->items->sum('quantity'),
            'line_count' => $cart->items->count(),
            'subtotal' => (int) $cart->subtotal,
            'discount_total' => (int) $cart->discount_total,
            'tax_total' => (int) $cart->tax_total,
            'shipping_total' => (int) $cart->shipping_total,
            'grand_total' => (int) $cart->grand_total,
            'coupon' => $cart->coupon?->code,
            'issues' => $issues,
        ];
    }

    private function lineIsAvailable(CartItem $item): bool
    {
        if (! $item->product || $item->product->trashed() || $item->product->status !== 'published') {
            return false;
        }

        $owner = $item->variation ?? $item->product;

        return $item->variation === null || $item->variation->is_active
            ? $this->inventory->availableQuantity($owner) >= (int) $item->quantity
            : false;
    }

    private function createGuestCart(): Cart
    {
        $token = $this->carts->newGuestToken();
        session()->put(self::SESSION_KEY, $token);

        return $this->carts->getOrCreateForToken($token);
    }
}

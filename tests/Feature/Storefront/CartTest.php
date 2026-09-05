<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\InventoryReservation;
use App\Models\Product;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Catalog\ProductVariantService;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

function cartRuntimeProduct(string $name, int $price = 1000, int $stock = 10, array $extra = []): Product
{
    $product = Product::query()->create(array_merge([
        'name' => $name,
        'slug' => (string) str($name)->slug(),
        'type' => 'simple',
        'price' => $price,
        'status' => 'published',
        'published_at' => now(),
        'manage_stock' => true,
        'stock_quantity' => $stock,
        'stock_status' => $stock > 0 ? 'in_stock' : 'out_of_stock',
    ], $extra));

    if ($product->type !== 'variable') {
        app(InventoryService::class)->setOnHand($product, $stock);
    }

    return $product;
}

function cartRuntimeVariableProduct(string $name = 'Cart Variable'): array
{
    $product = cartRuntimeProduct($name, 0, 0, ['type' => 'variable']);
    $attribute = Attribute::query()->create([
        'name' => 'Color '.$name,
        'slug' => 'color-'.str($name)->slug(),
        'type' => 'select',
        'is_variation' => true,
        'is_visible' => true,
    ]);
    $red = AttributeValue::query()->create([
        'attribute_id' => $attribute->id,
        'value' => 'Red',
        'slug' => 'red-'.str($name)->slug(),
    ]);
    $blue = AttributeValue::query()->create([
        'attribute_id' => $attribute->id,
        'value' => 'Blue',
        'slug' => 'blue-'.str($name)->slug(),
    ]);
    $product->attributes()->attach($attribute, ['sort_order' => 1]);
    $product->attributeValues()->attach([$red->id, $blue->id]);
    $suffix = str($name)->slug();
    $variation = app(ProductVariantService::class)->create(
        $product,
        ['price' => 1800, 'sku' => 'CART-VAR-RED-'.$suffix, 'stock_quantity' => 4],
        [$red->id],
    );
    $inactive = app(ProductVariantService::class)->create(
        $product,
        ['price' => 1900, 'sku' => 'CART-VAR-BLUE-'.$suffix, 'stock_quantity' => 4, 'is_active' => false],
        [$blue->id],
    );

    return compact('product', 'attribute', 'red', 'blue', 'variation', 'inactive');
}

test('guest can add a simple product through the detail form and sees authoritative cart state', function (): void {
    $product = cartRuntimeProduct('Guest Cart Product', 2500, 5);

    $this->get(route('storefront.products.show', $product))
        ->assertOk()
        ->assertSee('action="'.route('storefront.cart.items.store').'"', false)
        ->assertSee('name="product_id"', false);

    $response = $this->from(route('storefront.products.show', $product))
        ->post(route('storefront.cart.items.store'), [
            '_token' => csrf_token(),
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

    $response->assertRedirect(route('storefront.cart.show'))
        ->assertSessionHas('status');
    $cart = Cart::query()->where('token', session('storefront_cart_token'))->firstOrFail();
    expect($cart->user_id)->toBeNull()
        ->and($cart->items)->toHaveCount(1)
        ->and($cart->items->first()->unit_price)->toBe(2500)
        ->and($cart->grand_total)->toBe(5000)
        ->and(InventoryReservation::query()->count())->toBe(0);

    $this->get(route('storefront.cart.show'))
        ->assertOk()
        ->assertSee('Guest Cart Product')
        ->assertSee('۵,۰۰۰ ریال')
        ->assertSee('data-cart-count', false)
        ->assertSee('۲ کالا');
});

test('authenticated customers receive an owned cart and cannot tamper with another cart line', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $product = cartRuntimeProduct('Owned Cart Product');
    $otherCart = app(CartService::class)->getOrCreateForUser($other->id);
    app(CartService::class)->addItem($otherCart, $product, 1);
    $otherLine = $otherCart->fresh()->items->first();

    $this->actingAs($owner)
        ->post(route('storefront.cart.items.store'), [
            '_token' => csrf_token(),
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertRedirect(route('storefront.cart.show'));

    $ownerCart = Cart::query()->where('user_id', $owner->id)->where('status', 'active')->first();
    expect($ownerCart)->not->toBeNull()->and($ownerCart->id)->not->toBe($otherCart->id);

    $this->actingAs($owner)
        ->withSession(['_token' => csrf_token()])
        ->delete(route('storefront.cart.items.remove', ['item' => $otherLine->id]))
        ->assertRedirect(route('storefront.cart.show'))
        ->assertSessionHasErrors('cart');

    expect($otherLine->fresh())->not->toBeNull();
});

test('guest cart tokens isolate line mutations between browser sessions', function (): void {
    $product = cartRuntimeProduct('Guest Isolation Product');
    $guestB = app(CartService::class)->getOrCreateForToken('guest-b-token');
    app(CartService::class)->addItem($guestB, $product, 1);
    $lineB = $guestB->fresh()->items->first();

    $this->withSession(['storefront_cart_token' => 'guest-a-token'])
        ->delete(route('storefront.cart.items.remove', ['item' => $lineB->id]), ['_token' => csrf_token()])
        ->assertRedirect(route('storefront.cart.show'))
        ->assertSessionHasErrors('cart');

    expect($lineB->fresh())->not->toBeNull();
});

test('variable add requires an active variation belonging to the product', function (): void {
    $fixture = cartRuntimeVariableProduct();
    $other = cartRuntimeVariableProduct('Other Cart Variable');

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $fixture['product']->id,
        'quantity' => 1,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('cart');

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $fixture['product']->id,
        'variation_id' => $other['variation']->id,
        'quantity' => 1,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('cart');

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $fixture['product']->id,
        'variation_id' => $fixture['inactive']->id,
        'quantity' => 1,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('cart');

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $fixture['product']->id,
        'variation_id' => $fixture['variation']->id,
        'quantity' => 1,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHas('status');

    expect(Cart::query()->where('token', session('storefront_cart_token'))->firstOrFail()->items)->toHaveCount(1);
});

test('quantity validation and current availability are enforced without reservation', function (): void {
    $product = cartRuntimeProduct('Limited Cart Product', 900, 2);

    foreach ([0, -1, 'not-a-number'] as $quantity) {
        $this->post(route('storefront.cart.items.store'), [
            '_token' => csrf_token(),
            'product_id' => $product->id,
            'quantity' => $quantity,
        ])->assertSessionHasErrors('quantity');
    }

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $product->id,
        'quantity' => 3,
    ])->assertRedirect(route('storefront.cart.show'))->assertSessionHasErrors('cart');

    expect(InventoryReservation::query()->count())->toBe(0);
});

test('cart quantity update, line removal, clear, and empty state use server mutations', function (): void {
    $product = cartRuntimeProduct('Mutation Cart Product', 700, 10);
    $this->post(route('storefront.cart.items.store'), ['_token' => csrf_token(), 'product_id' => $product->id, 'quantity' => 2]);
    $cart = Cart::query()->where('token', session('storefront_cart_token'))->firstOrFail();
    $line = $cart->items->first();

    $this->patch(route('storefront.cart.items.update', ['item' => $line->id]), ['_token' => csrf_token(), 'quantity' => 4])
        ->assertRedirect(route('storefront.cart.show'));
    expect($line->fresh()->quantity)->toBe(4);

    $this->delete(route('storefront.cart.items.remove', ['item' => $line->id]), ['_token' => csrf_token()])
        ->assertRedirect(route('storefront.cart.show'));
    expect($line->fresh())->toBeNull();

    $this->post(route('storefront.cart.items.store'), ['_token' => csrf_token(), 'product_id' => $product->id, 'quantity' => 1]);
    $this->delete(route('storefront.cart.clear'), ['_token' => csrf_token()])
        ->assertRedirect(route('storefront.cart.show'));
    $this->get(route('storefront.cart.show'))->assertOk()->assertSee('سبد خرید شما خالی است');
    expect($cart->fresh()->items)->toBeEmpty()->and($cart->fresh()->grand_total)->toBe(0);
});

test('cart recalculation reflects a product becoming unavailable after it was added', function (): void {
    $product = cartRuntimeProduct('Changing Availability Product', 1000, 3);
    $this->post(route('storefront.cart.items.store'), ['_token' => csrf_token(), 'product_id' => $product->id, 'quantity' => 2]);
    app(InventoryService::class)->setOnHand($product, 0);

    $this->get(route('storefront.cart.show'))
        ->assertOk()
        ->assertSee('برخی کالاها دیگر قابل خرید نیستند')
        ->assertSee('این محصول دیگر موجود نیست');
});

test('converted cart is never mutated and a new active guest cart is used', function (): void {
    $product = cartRuntimeProduct('Converted Cart Product');
    $cart = app(CartService::class)->getOrCreateForToken('converted-session-token');
    app(CartService::class)->addItem($cart, $product, 1);
    app(CartService::class)->convert($cart);
    $this->withSession(['storefront_cart_token' => 'converted-session-token']);

    $this->post(route('storefront.cart.items.store'), [
        '_token' => csrf_token(),
        'product_id' => $product->id,
        'quantity' => 1,
    ])->assertRedirect(route('storefront.cart.show'));

    expect($cart->fresh()->status)->toBe('converted')
        ->and($cart->fresh()->items)->toHaveCount(1)
        ->and(Cart::query()->where('token', 'converted-session-token')->where('status', 'active')->count())->toBe(0)
        ->and(session('storefront_cart_token'))->not->toBe('converted-session-token');
});

test('cart forms use the web middleware boundary and render csrf fields', function (): void {
    $product = cartRuntimeProduct('CSRF Cart Product');

    $this->get(route('storefront.products.show', $product))
        ->assertOk()
        ->assertSee('name="_token"', false);
    expect(Route::getRoutes()->getByName('storefront.cart.items.store')->middleware())
        ->toContain('web');
});

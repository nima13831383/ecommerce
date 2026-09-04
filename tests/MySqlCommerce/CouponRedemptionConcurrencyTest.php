<?php

use App\Exceptions\CouponValidationException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\EnsuresMySqlTestDatabase;
use Tests\Support\Concurrency\ConcurrentProcessRunner;

uses(EnsuresMySqlTestDatabase::class);

it('redeems one coupon once under same-order MySQL contention', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = couponRedemptionFixture();

    expect(CouponUsage::query()
        ->where('coupon_id', $fixture['coupon']->id)
        ->where('order_id', $fixture['order']->id)
        ->count())->toBe(0)
        ->and($fixture['coupon']->usage_count)->toBe(0)
        ->and($fixture['coupon']->usage_limit)->toBe(1);

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('coupon_redeem', [
        'coupon_id' => $fixture['coupon']->id,
        'cart_id' => $fixture['cart']->id,
        'order_id' => $fixture['order']->id,
        'user_id' => $fixture['user']->id,
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('')
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    foreach (['A', 'B'] as $worker) {
        $result = $run['results'][$worker]['json'];

        expect($result['ok'])->toBeTrue()
            ->and($result['result']['coupon_id'])->toBe($fixture['coupon']->id)
            ->and($result['result']['order_id'])->toBe($fixture['order']->id)
            ->and($result['result']['user_id'])->toBe($fixture['user']->id)
            ->and($result['result']['discount_amount'])->toBe(25);
    }

    $usage = CouponUsage::query()
        ->where('coupon_id', $fixture['coupon']->id)
        ->where('order_id', $fixture['order']->id)
        ->sole();

    expect(CouponUsage::query()->where('coupon_id', $fixture['coupon']->id)->where('order_id', $fixture['order']->id)->count())->toBe(1)
        ->and($run['results']['A']['json']['result']['usage_id'])->toBe($usage->id)
        ->and($run['results']['B']['json']['result']['usage_id'])->toBe($usage->id)
        ->and(CouponUsage::query()->where('order_id', $fixture['order']->id)->distinct('coupon_id')->count('coupon_id'))->toBe(1)
        ->and($usage->user_id)->toBe($fixture['user']->id)
        ->and((int) $usage->discount_amount)->toBe(25)
        ->and($fixture['coupon']->fresh()->usage_count)->toBe(1);

});

it('replays same coupon redemption without creating another usage', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = couponRedemptionFixture();
    DB::commit();

    $service = app(CouponService::class);
    $first = $service->apply($fixture['coupon'], $fixture['cart'], $fixture['order'], $fixture['user']->id);
    $second = $service->apply($fixture['coupon'], $fixture['cart'], $fixture['order'], $fixture['user']->id);

    expect($first)->toBe(25)
        ->and($second)->toBe(25)
        ->and(CouponUsage::query()->where('coupon_id', $fixture['coupon']->id)->where('order_id', $fixture['order']->id)->count())->toBe(1)
        ->and($fixture['coupon']->fresh()->usage_count)->toBe(1);
});

it('enforces a global coupon usage limit for two different orders under MySQL contention', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = couponGlobalLimitFixture();

    expect(CouponUsage::query()->where('coupon_id', $fixture['coupon']->id)->count())->toBe(0)
        ->and($fixture['coupon']->usage_count)->toBe(0)
        ->and($fixture['coupon']->usage_limit)->toBe(1)
        ->and($fixture['coupon']->usage_limit - $fixture['coupon']->usage_count)->toBe(1);

    DB::commit();

    $run = app(ConcurrentProcessRunner::class)->run('coupon_redeem', [
        'coupon_id' => $fixture['coupon']->id,
        'worker_data' => [
            'A' => [
                'cart_id' => $fixture['cartA']->id,
                'order_id' => $fixture['orderA']->id,
                'user_id' => $fixture['userA']->id,
            ],
            'B' => [
                'cart_id' => $fixture['cartB']->id,
                'order_id' => $fixture['orderB']->id,
                'user_id' => $fixture['userB']->id,
            ],
        ],
    ]);

    expect($run['alive'])->toBeTrue()
        ->and($run['pids']['A'])->not->toBe('')
        ->and($run['pids']['B'])->not->toBe('')
        ->and($run['results']['A']['exit'])->toBe(0)
        ->and($run['results']['B']['exit'])->toBe(0);

    $successfulWorkers = collect($run['results'])->filter(
        fn (array $result): bool => $result['json']['ok'] === true,
    );
    $failedWorkers = collect($run['results'])->filter(
        fn (array $result): bool => $result['json']['ok'] === false,
    );

    expect($successfulWorkers)->toHaveCount(1)
        ->and($failedWorkers)->toHaveCount(1);

    $winner = $successfulWorkers->sole()['json'];
    $loser = $failedWorkers->sole()['json'];

    expect($winner['result']['coupon_id'])->toBe($fixture['coupon']->id)
        ->and($winner['result']['order_id'])->toBeIn([$fixture['orderA']->id, $fixture['orderB']->id])
        ->and($winner['result']['discount_amount'])->toBe(25)
        ->and($loser['exception']['class'])->toBe(CouponValidationException::class);

    $usage = CouponUsage::query()
        ->where('coupon_id', $fixture['coupon']->id)
        ->sole();

    expect(CouponUsage::query()->where('coupon_id', $fixture['coupon']->id)->count())->toBe(1)
        ->and($usage->order_id)->toBe($winner['result']['order_id'])
        ->and($fixture['coupon']->fresh()->usage_count)->toBe(1);
});

it('rejects a second distinct order after the global coupon limit is consumed sequentially', function (): void {
    $this->assertSafeMySqlTestDatabase();
    $fixture = couponGlobalLimitFixture();
    DB::commit();

    $service = app(CouponService::class);

    expect($service->apply($fixture['coupon'], $fixture['cartA'], $fixture['orderA'], $fixture['userA']->id))->toBe(25)
        ->and(fn () => $service->apply($fixture['coupon'], $fixture['cartB'], $fixture['orderB'], $fixture['userB']->id))
        ->toThrow(CouponValidationException::class)
        ->and(CouponUsage::query()->where('coupon_id', $fixture['coupon']->id)->count())->toBe(1)
        ->and($fixture['coupon']->fresh()->usage_count)->toBe(1);
});

/** @return array{user: User, product: Product, cart: Cart, coupon: Coupon, order: Order} */
function couponRedemptionFixture(): array
{
    $user = User::factory()->create();
    $product = Product::query()->create([
        'name' => 'Coupon redemption race product',
        'slug' => 'coupon-redemption-race-'.Str::lower(Str::random(12)),
        'sku' => 'COUPON-REDEMPTION-RACE-'.Str::upper(Str::random(12)),
        'type' => 'simple',
        'price' => 100,
        'status' => 'published',
    ]);
    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'token' => 'coupon-race-'.Str::lower(Str::random(16)),
        'status' => 'active',
        'currency' => 'IRR',
    ]);
    CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]);
    $cart->load('items');
    $coupon = Coupon::query()->create([
        'code' => 'COUPON-RACE-'.Str::upper(Str::random(12)),
        'type' => 'fixed_cart',
        'amount' => 25,
        'usage_limit' => 1,
        'usage_count' => 0,
        'is_active' => true,
    ]);
    $order = Order::query()->create([
        'order_number' => 'CPN-RACE-'.Str::upper(Str::random(12)),
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'customer_name' => $user->name,
        'customer_mobile' => $user->mobile ?? '09120000000',
        'customer_email' => $user->email,
        'currency' => 'IRR',
        'items_subtotal' => 100,
        'discount_total' => 0,
        'grand_total' => 100,
    ]);

    return compact('user', 'product', 'cart', 'coupon', 'order');
}

/** @return array{userA: User, userB: User, product: Product, cartA: Cart, cartB: Cart, coupon: Coupon, orderA: Order, orderB: Order} */
function couponGlobalLimitFixture(): array
{
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $product = Product::query()->create([
        'name' => 'Coupon global limit race product',
        'slug' => 'coupon-global-limit-race-'.Str::lower(Str::random(12)),
        'sku' => 'COUPON-GLOBAL-LIMIT-RACE-'.Str::upper(Str::random(12)),
        'type' => 'simple',
        'price' => 100,
        'status' => 'published',
    ]);
    $coupon = Coupon::query()->create([
        'code' => 'COUPON-GLOBAL-LIMIT-RACE-'.Str::upper(Str::random(12)),
        'type' => 'fixed_cart',
        'amount' => 25,
        'usage_limit' => 1,
        'usage_count' => 0,
        'is_active' => true,
    ]);

    [$cartA, $orderA] = couponGlobalLimitCartAndOrder($userA, $product, 'A');
    [$cartB, $orderB] = couponGlobalLimitCartAndOrder($userB, $product, 'B');

    return compact('userA', 'userB', 'product', 'cartA', 'cartB', 'coupon', 'orderA', 'orderB');
}

/** @return array{0: Cart, 1: Order} */
function couponGlobalLimitCartAndOrder(User $user, Product $product, string $worker): array
{
    $cart = Cart::query()->create([
        'user_id' => $user->id,
        'token' => "coupon-global-limit-race-{$worker}-".Str::lower(Str::random(16)),
        'status' => 'active',
        'currency' => 'IRR',
    ]);
    CartItem::query()->create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]);
    $cart->load('items');
    $order = Order::query()->create([
        'order_number' => "CPN-GLOBAL-{$worker}-".Str::upper(Str::random(12)),
        'user_id' => $user->id,
        'cart_id' => $cart->id,
        'customer_name' => $user->name,
        'customer_mobile' => $user->mobile ?? '09120000000',
        'customer_email' => $user->email,
        'currency' => 'IRR',
        'items_subtotal' => 100,
        'discount_total' => 0,
        'grand_total' => 100,
    ]);

    return [$cart, $order];
}

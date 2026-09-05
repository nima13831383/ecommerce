<?php

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Fulfillment\ShipmentService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderCreationContext;
use App\Services\Orders\OrderPricing;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Support\PersianNumber;
use Tests\Support\Payments\FakePaymentGateway;

beforeEach(function (): void {
    $this->fakeGateway = new FakePaymentGateway;
    $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([$this->fakeGateway]));
});

function storefrontOrderProduct(string $suffix = 'default', int $stock = 100): Product
{
    $product = Product::query()->create([
        'name' => "Order snapshot product {$suffix}",
        'slug' => "order-snapshot-{$suffix}",
        'sku' => "ORDER-SNAPSHOT-{$suffix}",
        'type' => 'simple',
        'price' => 250_000,
        'status' => 'published',
        'weight' => 2,
    ]);

    app(InventoryService::class)->setOnHand($product, $stock, reason: 'Storefront order fixture');

    return $product;
}

function storefrontOrderFor(User $user, Product $product, string $suffix = 'default'): Order
{
    return app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 1]],
        [
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_mobile' => '09120000000',
            'customer_email' => $user->email,
            'shipping_address' => [
                'first_name' => 'Ali',
                'last_name' => 'Customer',
                'mobile' => '09120000000',
                'province_name' => 'تهران',
                'city_name' => 'تهران',
                'postal_code' => '0123456789',
                'address_line' => "خیابان سفارش {$suffix}",
                'plaque' => '10',
                'unit' => '2',
            ],
            'billing_address' => [
                'first_name' => 'Ali',
                'last_name' => 'Customer',
                'mobile' => '09120000000',
                'province_name' => 'تهران',
                'city_name' => 'تهران',
                'postal_code' => '0123456789',
                'address_line' => "خیابان سفارش {$suffix}",
            ],
            'shipping_snapshot' => [
                'service' => 'pishtaz',
                'mode' => 'fixed',
                'payment_type' => 'online',
            ],
        ],
        context: new OrderCreationContext(pricing: new OrderPricing(
            shippingTotal: 20_000,
            shippingSnapshot: [
                'service' => 'pishtaz',
                'mode' => 'fixed',
                'payment_type' => 'online',
            ],
        )),
    );
}

test('guests are redirected and customers see only their own paginated orders', function (): void {
    $this->get(route('storefront.account.orders'))->assertRedirect(route('login'));

    $owner = User::factory()->create();
    $other = User::factory()->create();
    $product = storefrontOrderProduct('list');
    $first = storefrontOrderFor($owner, $product, 'first');
    storefrontOrderFor($other, $product, 'foreign');

    $response = $this->actingAs($owner)->get(route('storefront.account.orders'));
    $response->assertOk()
        ->assertSee($first->order_number)
        ->assertSee(PersianNumber::money($first->grand_total))
        ->assertDontSee('foreign');

    for ($index = 0; $index < 11; $index++) {
        storefrontOrderFor($owner, $product, "page-{$index}");
    }

    $page = $this->actingAs($owner)->get(route('storefront.account.orders', ['page' => 2]));
    $page->assertOk()->assertSee('صفحه‌بندی');
});

test('empty orders and account dashboard use real order data without fake metrics', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('storefront.account.orders'))
        ->assertOk()
        ->assertSee('هنوز سفارشی ثبت نکرده‌اید.')
        ->assertSee('مشاهده محصولات');

    $order = storefrontOrderFor($user, storefrontOrderProduct('dashboard'), 'dashboard');
    $this->actingAs($user)->get(route('storefront.account'))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('مشاهده همه (1)')
        ->assertDontSee('کیف پول')
        ->assertDontSee('امتیاز وفاداری');
});

test('order detail renders snapshots, statuses, timeline, safe payment retry, and survives product deletion', function (): void {
    $user = User::factory()->create();
    $product = storefrontOrderProduct('detail');
    ProductImage::query()->create(['product_id' => $product->id, 'path' => 'products/order-detail.jpg', 'alt' => 'تصویر سفارش', 'is_primary' => true]);
    $order = storefrontOrderFor($user, $product, 'detail');
    app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment, $user->id, 'Payment requested.');

    $response = $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]));
    $response->assertOk()
        ->assertSee($order->order_number)
        ->assertSee($order->items->sole()->product_name)
        ->assertSee('SKU: '.$order->items->sole()->sku)
        ->assertSee('خیابان سفارش detail')
        ->assertSee('روش ارسال')
        ->assertSee('در انتظار پرداخت')
        ->assertSee('ادامه به پرداخت')
        ->assertDontSee('Payment requested.')
        ->assertDontSee('gateway_response')
        ->assertDontSee('inventory_reservations')
        ->assertDontSee('idempotency_key')
        ->assertDontSee('admin_note');

    $product->delete();
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertOk()
        ->assertSee($order->items->sole()->product_name)
        ->assertDontSee('محصول در دسترس نیست');
});

test('foreign numeric and public order identities are not disclosed', function (): void {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $order = storefrontOrderFor($owner, storefrontOrderProduct('idor'), 'idor');

    $this->actingAs($other)->get(route('storefront.account.orders.show', ['order' => $order->id]))->assertNotFound();
    $this->actingAs($other)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))->assertNotFound();
    $this->actingAs($owner)->get(route('storefront.account.orders.show', ['order' => $order->id]))->assertOk()->assertSee($order->order_number);
});

test('payment action is absent for paid and cancelled orders', function (): void {
    $user = User::factory()->create();
    $paid = storefrontOrderFor($user, storefrontOrderProduct('paid'), 'paid');
    $paid->applyPaymentStatus(OrderPaymentStatus::Paid, $paid->grand_total);
    $cancelled = storefrontOrderFor($user, storefrontOrderProduct('cancelled'), 'cancelled');
    app(OrderService::class)->transitionStatus($cancelled, OrderStatus::Cancelled, $user->id, 'Customer order cancelled.');

    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $paid->order_number]))
        ->assertOk()
        ->assertDontSee('ادامه به پرداخت');
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $cancelled->order_number]))
        ->assertOk()
        ->assertDontSee('ادامه به پرداخت');
});

test('customer shipment presentation follows real lifecycle states and has no mutation route', function (): void {
    $user = User::factory()->create();
    $product = storefrontOrderProduct('shipment');
    $order = storefrontOrderFor($user, $product, 'shipment');

    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertOk()
        ->assertSee('سفارش شما در حال پردازش است');

    $shipments = app(ShipmentService::class);
    $shipment = $shipments->ensure($order);
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertSee('در انتظار پردازش');

    $shipments->transition($shipment, ShipmentStatus::Ready);
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertSee('آماده ارسال');

    $order->refresh();
    $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
    app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment);
    app(OrderService::class)->transitionStatus($order, OrderStatus::Processing);
    app(OrderService::class)->commitInventoryForOrder($order);
    $shipment = $shipment->fresh();
    $shipments->transition($shipment, ShipmentStatus::Shipped);
    $shipment = $shipment->fresh();
    $shipments->updateTracking($shipment, 'TRK-123456', 'https://carrier.test/TRK-123456');
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertSee('ارسال شده')
        ->assertSee('TRK-123456');

    $shipments->transition($shipment->fresh(), ShipmentStatus::Delivered);
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $order->order_number]))
        ->assertSee('تحویل شده');

    $cancelOrder = storefrontOrderFor($user, storefrontOrderProduct('shipment-cancel'), 'shipment-cancel');
    $cancelShipment = $shipments->ensure($cancelOrder);
    $shipments->transition($cancelShipment, ShipmentStatus::Cancelled);
    $this->actingAs($user)->get(route('storefront.account.orders.show', ['order' => $cancelOrder->order_number]))
        ->assertSee('لغو شده');

    $this->actingAs($user)->post('/account/orders/'.$order->order_number.'/shipment')->assertNotFound();
    expect(Shipment::query()->where('order_id', $order->id)->count())->toBe(1);
});

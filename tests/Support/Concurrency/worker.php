<?php

use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Checkout\CheckoutInput;
use App\Services\Checkout\CheckoutService;
use App\Services\CouponService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\Console\Kernel;
use Tests\Support\Payments\FakePaymentGateway;

require dirname(__DIR__, 3).'/vendor/autoload.php';
$payload = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$app = require dirname(__DIR__, 3).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$db = (string) config('database.connections.'.config('database.default').'.database');
if (app()->environment() !== 'testing' || config('database.default') !== 'mysql' || ! str_ends_with($db, '_testing') || $db === 'ecommerce') {
    fwrite(STDOUT, json_encode(['ok' => false, 'exception' => ['class' => LogicException::class, 'message' => 'Unsafe MySQL test worker configuration.']]).PHP_EOL);
    exit(2);
}
$barrier = $payload['barrier'];
$worker = $payload['worker'];
$started = microtime(true);
$checkoutUser = null;
$checkoutInput = null;
$payment = null;
$cancelOrder = null;
$coupon = null;
$couponCart = null;
$couponOrder = null;
if (($payload['operation'] ?? null) === 'checkout') {
    $checkoutData = $payload['data'];
    $checkoutUser = User::query()->findOrFail((int) $checkoutData['user_id']);
    $checkoutInput = new CheckoutInput(
        cartId: (int) $checkoutData['cart_id'],
        shippingAddressId: (int) $checkoutData['shipping_address_id'],
        billingAddressId: isset($checkoutData['billing_address_id']) ? (int) $checkoutData['billing_address_id'] : null,
        shippingService: (string) $checkoutData['shipping_service'],
        shippingPaymentType: (string) $checkoutData['shipping_payment_type'],
        idempotencyKey: (string) $checkoutData['idempotency_key'],
    );
}
if (($payload['operation'] ?? null) === 'payment_verify') {
    $payment = Payment::query()->findOrFail((int) $payload['data']['payment_id']);
    app()->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([new FakePaymentGateway]));
}
if (($payload['operation'] ?? null) === 'order_cancel') {
    $cancelOrder = Order::query()->findOrFail((int) $payload['data']['order_id']);
}
if (($payload['operation'] ?? null) === 'coupon_redeem') {
    $couponData = $payload['data'];
    $coupon = Coupon::query()->findOrFail((int) $couponData['coupon_id']);
    $couponCart = Cart::query()->findOrFail((int) $couponData['cart_id']);
    $couponOrder = Order::query()->findOrFail((int) $couponData['order_id']);
}
file_put_contents("{$barrier}/{$worker}.ready", (string) getmypid());
$deadline = microtime(true) + 10;
while (! file_exists("{$barrier}/release")) {
    if (microtime(true) >= $deadline) {
        throw new RuntimeException('Barrier timeout.');
    } usleep(10000);
}
try {
    $result = match ($payload['operation']) {
        'barrier_self_test' => ['operation' => 'barrier_self_test'],
        'inventory_reserve' => ['reservation_id' => app(InventoryService::class)->reserve(
            Product::query()->findOrFail((int) $payload['data']['product_id']),
            (int) $payload['data']['quantity'], now()->addMinutes(5), 'mysql_concurrency', (string) $payload['data']['reference_id'],
        )->id],
        'checkout' => (static function () use ($checkoutUser, $checkoutInput): array {
            $checkoutResult = app(CheckoutService::class)->placeOrder($checkoutUser, $checkoutInput);

            return [
                'order_id' => $checkoutResult->order?->id,
                'order_number' => $checkoutResult->order?->order_number,
            ];
        })(),
        'payment_verify' => (static function () use ($payment): array {
            $verifiedPayment = app(PaymentService::class)->verify($payment);

            return [
                'payment_id' => $verifiedPayment->id,
                'payment_status' => $verifiedPayment->status->value,
                'order_id' => $verifiedPayment->order_id,
                'order_status' => $verifiedPayment->order()->value('status'),
            ];
        })(),
        'order_cancel' => (static function () use ($cancelOrder, $payload): array {
            $cancelledOrder = app(OrderService::class)->transitionStatus(
                $cancelOrder,
                OrderStatus::Cancelled,
                comment: (string) ($payload['data']['reason'] ?? 'Concurrency cancellation test.'),
            );

            return [
                'order_id' => $cancelledOrder->id,
                'order_status' => $cancelledOrder->status->value,
            ];
        })(),
        'coupon_redeem' => (static function () use ($coupon, $couponCart, $couponOrder, $payload): array {
            $discountAmount = app(CouponService::class)->apply(
                $coupon,
                $couponCart,
                $couponOrder,
                isset($payload['data']['user_id']) ? (int) $payload['data']['user_id'] : null,
            );
            $usage = CouponUsage::query()
                ->where('coupon_id', $coupon->id)
                ->where('order_id', $couponOrder->id)
                ->firstOrFail();

            return [
                'usage_id' => $usage->id,
                'coupon_id' => $usage->coupon_id,
                'order_id' => $usage->order_id,
                'user_id' => $usage->user_id,
                'discount_amount' => (int) $discountAmount,
            ];
        })(),
        default => throw new InvalidArgumentException('Unknown worker operation.'),
    };
    echo json_encode(['ok' => true, 'worker' => $worker, 'pid' => getmypid(), 'started_at' => $started, 'released_at' => microtime(true), 'result' => $result, 'exception' => null], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'worker' => $worker, 'pid' => getmypid(), 'result' => null, 'exception' => ['class' => $e::class, 'message' => $e->getMessage()]], JSON_THROW_ON_ERROR).PHP_EOL;
}

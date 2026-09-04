<?php

use App\Contracts\Payments\ZarinPalClientInterface;
use App\Enums\InventoryReservationStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentCallbackSigner;
use App\Services\Payments\PaymentGatewayRegistry;
use App\Services\Payments\PaymentService;
use App\Services\Payments\ZarinPalPaymentGateway;
use App\Services\Storefront\StorefrontPaymentGateway;
use Http\Client\Common\HttpMethodsClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use ZarinPal\Sdk\Endpoint\PaymentGateway\PaymentGateway;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\RequestRequest;
use ZarinPal\Sdk\Endpoint\PaymentGateway\RequestTypes\VerifyRequest;
use ZarinPal\Sdk\Options;
use ZarinPal\Sdk\ZarinPal;

class StubZarinPalClient implements ZarinPalClientInterface
{
    public array $requestArguments = [];

    public array $verifyArguments = [];

    public array $requestResponse = [
        'code' => 100,
        'authority' => 'A00000000000000000000000000000000000',
        'message' => 'Success',
        'fee_type' => null,
        'fee' => 0,
        'amount' => null,
    ];

    public array $verifyResponse = [
        'code' => 100,
        'ref_id' => '123456789',
        'card_pan' => null,
        'card_hash' => null,
        'fee_type' => null,
        'fee' => 0,
        'message' => 'Success',
    ];

    public function request(
        int $amount,
        string $description,
        string $callbackUrl,
        string $currency,
        ?string $mobile = null,
        ?string $email = null,
    ): array {
        $this->requestArguments = compact('amount', 'description', 'callbackUrl', 'currency', 'mobile', 'email');

        return $this->requestResponse;
    }

    public function verify(string $authority, int $amount): array
    {
        $this->verifyArguments = compact('authority', 'amount');

        return $this->verifyResponse;
    }

    public function redirectUrl(string $authority): string
    {
        return 'https://sandbox.zarinpal.com/pg/StartPay/'.$authority;
    }
}

beforeEach(function (): void {
    config([
        'payment.storefront_gateway' => 'zarinpal',
        'payment.gateways.zarinpal.merchant_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'payment.gateways.zarinpal.sandbox' => true,
        'payment.gateways.zarinpal.currency' => 'IRR',
    ]);

    $this->zarinClient = new StubZarinPalClient;
    $this->zarinGateway = new ZarinPalPaymentGateway($this->zarinClient, new PaymentCallbackSigner);
    $this->app->instance(PaymentGatewayRegistry::class, new PaymentGatewayRegistry([$this->zarinGateway]));
});

function zarinPalTestOrder(string $suffix = 'default', int $price = 25_000): array
{
    $user = User::factory()->create();
    $product = Product::query()->create([
        'name' => 'ZarinPal product '.$suffix,
        'slug' => 'zarinpal-'.$suffix.'-'.fake()->unique()->numerify('###'),
        'sku' => 'ZP-'.fake()->unique()->numerify('#####'),
        'type' => 'simple',
        'price' => $price,
        'status' => 'published',
        'stock_quantity' => 5,
        'stock_status' => 'in_stock',
    ]);
    app(InventoryService::class)->setOnHand($product, 5, reason: 'ZarinPal test setup');

    $order = app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 1]],
        [
            'user_id' => $user->id,
            'customer_name' => $user->name,
            'customer_mobile' => '09120000000',
            'customer_email' => $user->email,
        ],
    );

    $payment = Payment::query()->create([
        'payment_number' => 'PAY-ZP-'.fake()->unique()->numerify('#####'),
        'order_id' => $order->id,
        'user_id' => $user->id,
        'method' => 'online_gateway',
        'gateway' => 'zarinpal',
        'amount' => $order->grand_total,
        'currency' => 'IRR',
        'status' => PaymentStatus::Pending,
    ]);

    return [$user, $product, $order, $payment];
}

test('zarinpal is registered only when a valid merchant id is configured', function (): void {
    $this->app->forgetInstance(PaymentGatewayRegistry::class);

    expect(app(PaymentGatewayRegistry::class)->gateway('zarinpal'))->toBeInstanceOf(ZarinPalPaymentGateway::class);

    config(['payment.gateways.zarinpal.merchant_id' => null]);
    $this->app->forgetInstance(PaymentGatewayRegistry::class);

    expect(fn () => app(PaymentGatewayRegistry::class)->gateway('zarinpal'))->toThrow(DomainException::class);
});

test('initiation sends the persisted integer rial amount and sdk redirect details', function (): void {
    [$user, $product, $order, $payment] = zarinPalTestOrder('request');
    $payment->load('order');

    $result = $this->zarinGateway->initiate($payment);

    expect($result->successful)->toBeTrue()
        ->and($result->providerPaymentIdentifier)->toBe('A00000000000000000000000000000000000')
        ->and($result->redirectUrl)->toContain('/pg/StartPay/')
        ->and($this->zarinClient->requestArguments['amount'])->toBe($order->grand_total)
        ->and($this->zarinClient->requestArguments['currency'])->toBe('IRR')
        ->and($this->zarinClient->requestArguments['callbackUrl'])->toContain('/payment/callback/'.$payment->payment_number)
        ->and($this->zarinClient->requestArguments['description'])->toContain($order->order_number);
});

test('verification maps codes 100 and 101 to idempotent provider success', function (): void {
    [, , , $payment] = zarinPalTestOrder('verify');

    $payment->forceFill(['authority' => 'A00000000000000000000000000000000000'])->save();
    $normal = $this->zarinGateway->verify($payment);
    expect($normal->verified)->toBeTrue()->and($normal->providerReference)->toBe('123456789');

    $this->zarinClient->verifyResponse['code'] = 101;
    $replay = $this->zarinGateway->verify($payment);
    expect($replay->verified)->toBeTrue()->and($replay->amount)->toBe($payment->amount);
});

test('provider failures are normalized without exposing sdk exceptions', function (): void {
    [, , , $payment] = zarinPalTestOrder('failure');
    $this->zarinClient->requestResponse = ['code' => 400, 'authority' => null];

    $result = $this->zarinGateway->initiate($payment);
    expect($result->successful)->toBeFalse()->and($result->failureReason)->toBe('ZarinPal payment request was rejected.');

    $this->zarinClient->verifyResponse = ['code' => 10101];
    $verification = $this->zarinGateway->verify($payment);
    expect($verification->verified)->toBeFalse()->and($verification->failureReason)->toBe('ZarinPal payment could not be verified.');
});

test('storefront initiation redirects to zarinpal and callback queries cannot authorize verification', function (): void {
    [$user, $product, $order] = zarinPalTestOrder('http');

    $response = $this->actingAs($user)->post(route('storefront.payment.initiate', ['order' => $order->order_number]), [
        'amount' => 1,
    ]);

    $payment = Payment::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    $response->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/A00000000000000000000000000000000000');
    expect($this->zarinClient->requestArguments['amount'])->toBe($order->grand_total)
        ->and($payment->authority)->toBe('A00000000000000000000000000000000000');

    $this->actingAs($user)
        ->get(route('storefront.payment.return', ['payment' => $payment->id]).'?Status=OK&Authority=wrong')
        ->assertOk()
        ->assertSee('اطلاعات بازگشت پرداخت معتبر نیست');
    expect($this->zarinClient->verifyArguments)->toBe([]);

    $this->actingAs($user)
        ->get(route('storefront.payment.return', ['payment' => $payment->id]).'?Status=NOK&Authority='.$payment->authority)
        ->assertOk()
        ->assertSee('پرداخت توسط درگاه تکمیل نشد');
    expect($this->zarinClient->verifyArguments)->toBe([]);
});

test('signed provider callback verifies without requiring a storefront session', function (): void {
    [, $product, $order, $payment] = zarinPalTestOrder('callback');
    $authority = 'A00000000000000000000000000000000000';
    $payment->applyStatus(PaymentStatus::Processing, ['authority' => $authority]);
    app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment);

    $url = route('storefront.payment.callback', [
        'payment' => $payment->payment_number,
        'signature' => (new PaymentCallbackSigner)->sign($payment->payment_number),
    ]);
    $response = $this->get($url.'&Status=OK&Authority='.$authority);

    $response->assertRedirect(route('storefront.payment.result', ['payment' => $payment->id]));
    expect($payment->fresh()->status)->toBe(PaymentStatus::Paid)
        ->and($product->fresh()->stock_quantity)->toBe(4);
});

test('payment service uses zarinpal verification and commits inventory only once', function (): void {
    [$user, $product, $order, $payment] = zarinPalTestOrder('service');
    $payment->applyStatus(PaymentStatus::Processing, ['authority' => 'A00000000000000000000000000000000000']);
    app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment);
    $service = app(PaymentService::class);
    $before = InventoryTransaction::query()->count();

    $first = $service->verify($payment);
    $second = $service->verify($first);

    expect($first->status)->toBe(PaymentStatus::Paid)
        ->and($second->status)->toBe(PaymentStatus::Paid)
        ->and($product->fresh()->stock_quantity)->toBe(4)
        ->and(InventoryTransaction::query()->count())->toBe($before + 1)
        ->and($payment->fresh()->reference_id)->toBe('123456789');
});

test('zarinpal verification failure leaves reservation and stock untouched', function (): void {
    [, $product, $order, $payment] = zarinPalTestOrder('service-failure');
    $payment->applyStatus(PaymentStatus::Processing, ['authority' => 'A00000000000000000000000000000000000']);
    app(OrderService::class)->transitionStatus($order, OrderStatus::AwaitingPayment);
    $this->zarinClient->verifyResponse['code'] = 10101;

    $result = app(PaymentService::class)->verify($payment);

    expect($result->status)->toBe(PaymentStatus::Failed)
        ->and($product->fresh()->stock_quantity)->toBe(5)
        ->and($order->items()->sole()->inventoryReservation->fresh()->status)->toBe(InventoryReservationStatus::Active);
});

test('missing merchant id fails closed and fake remains unavailable in production', function (): void {
    config(['payment.gateways.zarinpal.merchant_id' => null]);
    $service = new StorefrontPaymentGateway(new PaymentGatewayRegistry([]));
    expect($service->alias())->toBeNull();

    config(['payment.storefront_gateway' => 'fake']);
    app()->detectEnvironment(fn (): string => 'production');
    try {
        expect((new StorefrontPaymentGateway(new PaymentGatewayRegistry([])))->alias())->toBeNull();
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('production zarinpal configuration does not register the fake gateway', function (): void {
    config(['payment.gateways.zarinpal.sandbox' => false]);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $registry = app(PaymentGatewayRegistry::class);

        expect($registry->gateway('zarinpal'))->toBeInstanceOf(ZarinPalPaymentGateway::class)
            ->and(fn () => $registry->gateway('fake'))->toThrow(DomainException::class);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('production refuses a sandbox zarinpal configuration', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    try {
        $this->app->forgetInstance(PaymentGatewayRegistry::class);

        expect(fn () => app(PaymentGatewayRegistry::class)->gateway('zarinpal'))->toThrow(DomainException::class);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});

test('official sdk request verify and redirect contract remains compatible with v2', function (): void {
    $client = Mockery::mock(HttpMethodsClientInterface::class);
    $client->shouldReceive('post')->twice()->andReturnUsing(function (string $url, array $headers, string $body): ResponseInterface {
        $stream = Mockery::mock(StreamInterface::class);
        $stream->shouldReceive('getContents')->andReturn(json_encode([
            'data' => str_contains($url, 'verify')
                ? ['code' => 100, 'ref_id' => '987654']
                : ['code' => 100, 'authority' => 'A00000000000000000000000000000000000'],
            'errors' => [],
        ]));
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getStatusCode')->andReturn(200);
        $response->shouldReceive('getBody')->andReturn($stream);

        return $response;
    });

    $sdk = new ZarinPal(new Options([
        'merchant_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'sandbox' => true,
    ]));
    $sdk->setHttpClient($client);
    $gateway = new PaymentGateway($sdk);

    $request = $gateway->request(new RequestRequest([
        'amount' => 18_500_000,
        'description' => 'SDK contract',
        'callback_url' => 'https://example.test/callback',
        'currency' => 'IRR',
    ]));
    $verify = $gateway->verify(new VerifyRequest([
        'amount' => 18_500_000,
        'authority' => $request->authority,
    ]));

    expect($request->code)->toBe(100)
        ->and($verify->code)->toBe(100)
        ->and($gateway->getRedirectUrl($request->authority))->toStartWith('https://sandbox.zarinpal.com/');
});

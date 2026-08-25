<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\Payments\Support\PaymentPresentation;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\User;
use App\Policies\PaymentPolicy;
use App\Services\Orders\OrderService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function paymentAdminUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function paymentOperationsOrder(): Order
{
    $product = Product::query()->create([
        'name' => 'محصول پرداخت',
        'slug' => 'payment-operations-product-'.fake()->unique()->numerify('###'),
        'type' => 'simple',
        'sku' => 'PAY-OPS-'.fake()->unique()->numerify('####'),
        'price' => 250_000,
    ]);
    $product->forceFill(['stock_quantity' => 10, 'stock_status' => 'in_stock'])->save();

    return app(OrderService::class)->create(
        [['product_id' => $product->id, 'quantity' => 1]],
        [
            'customer_name' => 'مشتری پرداخت',
            'customer_mobile' => '09121111111',
            'customer_email' => 'payment@example.test',
        ],
    );
}

function paymentOperationsAttempt(Order $order, array $attributes = []): Payment
{
    return Payment::query()->create(array_replace([
        'payment_number' => 'PAY-OPS-'.fake()->unique()->numerify('#####'),
        'order_id' => $order->id,
        'method' => 'online_gateway',
        'gateway' => 'test-gateway',
        'status' => PaymentStatus::Failed,
        'currency' => 'IRR',
        'amount' => $order->grand_total,
        'paid_amount' => 0,
        'refunded_amount' => 0,
        'reconciliation_required' => false,
    ], $attributes));
}

test('payment list and detail require read permissions and expose no generic mutation pages', function (): void {
    $payment = paymentOperationsAttempt(paymentOperationsOrder());

    $this->actingAs(User::factory()->create())
        ->get('/admin/payments')
        ->assertForbidden();

    $admin = paymentAdminUser(['payments.viewAny', 'payments.view']);

    $this->actingAs($admin)
        ->get('/admin/payments')
        ->assertOk();

    $this->actingAs($admin)
        ->get("/admin/payments/{$payment->id}")
        ->assertOk();

    expect(PaymentResource::getPages())->toHaveKeys(['index', 'view'])
        ->and(PaymentResource::getPages())->not->toHaveKey('create')
        ->and(PaymentResource::getPages())->not->toHaveKey('edit');
});

test('super-admin bypasses payment read permissions through the centralized authorization hook', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::findOrCreate('super-admin', 'web'));

    $this->actingAs($superAdmin)
        ->get('/admin/payments')
        ->assertOk();
});

test('payment transactions and historical attempts remain available to operations staff', function (): void {
    $order = paymentOperationsOrder();
    $failed = paymentOperationsAttempt($order);
    $successful = paymentOperationsAttempt($order, [
        'status' => PaymentStatus::Paid,
        'paid_amount' => $order->grand_total,
        'reference_id' => 'REF-123',
        'verified_at' => now(),
    ]);
    PaymentTransaction::query()->create([
        'payment_id' => $successful->id,
        'type' => 'verify',
        'status' => 'success',
        'amount' => $successful->amount,
        'authority' => 'AUTH-123',
        'reference_id' => 'REF-123',
        'response_payload' => ['token' => 'do-not-show', 'authority' => 'AUTH-123'],
    ]);

    $admin = paymentAdminUser(['payments.viewAny', 'payments.view']);

    $this->actingAs($admin)
        ->get("/admin/payments/{$successful->id}")
        ->assertOk();

    expect($order->payments()->pluck('id')->all())->toContain($failed->id, $successful->id)
        ->and($successful->transactions()->count())->toBe(1);
});

test('reconciliation warnings surface cancelled orders and invalid reservation coverage without mutating state', function (): void {
    $order = paymentOperationsOrder();
    app(OrderService::class)->transitionStatus($order, OrderStatus::Cancelled, comment: 'لغو آزمایشی');
    $payment = paymentOperationsAttempt($order, [
        'status' => PaymentStatus::Paid,
        'paid_amount' => $order->grand_total,
        'reconciliation_required' => true,
        'verified_at' => Carbon::now(),
    ]);

    expect(PaymentPresentation::warnings($payment->fresh('order.items.inventoryReservation')))
        ->toContain('این پرداخت نیازمند بررسی و تطبیق دستی است.')
        ->toContain('پرداخت تأیید شده اما سفارش مرتبط لغو شده است.')
        ->toContain('پرداخت تأیید شده اما پوشش رزرو موجودی سفارش معتبر یا قطعی نیست.');
});

test('diagnostic metadata redacts sensitive keys but preserves safe payment identifiers', function (): void {
    $metadata = PaymentPresentation::safeMetadata([
        'token' => 'secret-token',
        'card_pan' => '1234',
        'authority' => 'AUTH-123',
        'nested' => ['password' => 'secret-password', 'message' => 'safe'],
    ]);

    expect($metadata)
        ->toContain('اطلاعات حساس حذف شد')
        ->toContain('AUTH-123')
        ->toContain('safe')
        ->not->toContain('secret-token')
        ->not->toContain('secret-password');
});

test('payment policy and model prevent critical mutation paths', function (): void {
    $order = paymentOperationsOrder();
    $payment = paymentOperationsAttempt($order);
    $admin = paymentAdminUser(['payments.viewAny', 'payments.view']);
    $policy = app(PaymentPolicy::class);

    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->view($admin, $payment))->toBeTrue()
        ->and($policy->create($admin))->toBeFalse()
        ->and($policy->update($admin, $payment))->toBeFalse()
        ->and($policy->delete($admin, $payment))->toBeFalse()
        ->and($policy->deleteAny($admin))->toBeFalse();

    $payment->status = PaymentStatus::Paid;

    expect(fn () => $payment->save())->toThrow(LogicException::class);
});

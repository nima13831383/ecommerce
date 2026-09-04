<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PaymentInitiationRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Payments\PaymentCallbackSigner;
use App\Services\Payments\PaymentService;
use App\Services\Storefront\StorefrontPaymentGateway;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly StorefrontPaymentGateway $gateway,
        private readonly PaymentCallbackSigner $callbackSigner,
    ) {}

    public function initiate(PaymentInitiationRequest $request, string $order): RedirectResponse
    {
        $model = $this->ownedOrder($request, $order);
        $gateway = $this->gateway->alias();

        if ($gateway === null) {
            return back()->withErrors(['payment' => 'درگاه پرداخت هنوز تنظیم نشده است.']);
        }

        if ($model->payment_status === OrderPaymentStatus::Paid || $model->status === OrderStatus::Cancelled) {
            return back()->withErrors(['payment' => 'این سفارش دیگر قابل پرداخت نیست.']);
        }

        $key = $this->initiationKey($model->id);

        try {
            $payment = $this->payments->initiate($model, $gateway, $key);
        } catch (DomainException|ModelNotFoundException) {
            return back()->withErrors(['payment' => 'امکان آغاز پرداخت برای این سفارش وجود ندارد.']);
        }

        if ($payment->status === PaymentStatus::Failed) {
            session()->forget($this->sessionKey($model->id));

            return redirect()->route('storefront.payment.result', ['payment' => $payment->id]);
        }

        if ($gateway === 'fake' && app()->environment(['local', 'testing'])) {
            return redirect()->route('storefront.payment.return', ['payment' => $payment->id]);
        }

        $redirectUrl = data_get($payment->gateway_response, 'redirect_url');
        if (is_string($redirectUrl) && filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
            return redirect()->away($redirectUrl);
        }

        return redirect()->route('storefront.payment.result', ['payment' => $payment->id]);
    }

    public function paymentReturn(Request $request, string $payment): View
    {
        $model = $this->ownedPayment($request, $payment);
        $error = null;

        $callbackAuthority = $request->query('Authority');
        if (is_string($callbackAuthority) && $callbackAuthority !== '' && ! hash_equals((string) $model->authority, $callbackAuthority)) {
            return $this->resultView($model, 'اطلاعات بازگشت پرداخت معتبر نیست.');
        }

        $callbackStatus = strtoupper((string) $request->query('Status', ''));
        if ($callbackStatus !== '' && $callbackStatus !== 'OK') {
            session()->forget($this->sessionKey((int) $model->order_id));

            return $this->resultView($model, 'پرداخت توسط درگاه تکمیل نشد.');
        }

        if (! in_array($model->status, [PaymentStatus::Paid, PaymentStatus::Failed], true)) {
            try {
                $model = $this->payments->verify($model);
                if ($model->status === PaymentStatus::Failed) {
                    session()->forget($this->sessionKey((int) $model->order_id));
                }
            } catch (DomainException|ModelNotFoundException) {
                $error = 'وضعیت پرداخت قابل تأیید نیست؛ لطفاً دوباره تلاش کنید.';
            }
        }

        return $this->resultView($model, $error);
    }

    public function providerCallback(Request $request, string $payment): RedirectResponse
    {
        $model = Payment::query()->where('payment_number', $payment)->firstOrFail();
        abort_unless($model->gateway === 'zarinpal' && $this->callbackSigner->valid($payment, $request->query('signature')), 404);

        $authority = $request->query('Authority');
        if (! is_string($authority) || $authority === '' || ! hash_equals((string) $model->authority, $authority)) {
            return redirect()->route('storefront.payment.result', ['payment' => $model->id])
                ->withErrors(['payment' => 'اطلاعات بازگشت پرداخت معتبر نیست.']);
        }

        $status = strtoupper((string) $request->query('Status', ''));
        if ($status !== 'OK') {
            session()->forget($this->sessionKey((int) $model->order_id));
        }

        if ($status === 'OK' && ! in_array($model->status, [PaymentStatus::Paid, PaymentStatus::Failed], true)) {
            try {
                $this->payments->verify($model);
            } catch (DomainException|ModelNotFoundException) {
                return redirect()->route('storefront.payment.result', ['payment' => $model->id])
                    ->withErrors(['payment' => 'وضعیت پرداخت قابل تأیید نیست؛ لطفاً دوباره تلاش کنید.']);
            }
        }

        return redirect()->route('storefront.payment.result', ['payment' => $model->id]);
    }

    public function result(Request $request, string $payment): View
    {
        return $this->resultView($this->ownedPayment($request, $payment));
    }

    private function resultView(Payment $payment, ?string $error = null): View
    {
        $payment->load('order');
        $state = $payment->reconciliation_required
            ? 'review'
            : match ($payment->status) {
                PaymentStatus::Paid => 'success',
                PaymentStatus::Failed => 'failed',
                default => 'pending',
            };

        return view('storefront.payment.result', [
            'payment' => [
                'id' => (int) $payment->id,
                'status' => $payment->status->value,
                'amount' => (int) $payment->amount,
                'order_number' => $payment->order?->order_number,
                'order_id' => (int) $payment->order_id,
                'created_at' => $payment->created_at,
            ],
            'state' => $state,
            'error' => $error,
            'title' => 'نتیجه پرداخت | لوکسیر',
        ]);
    }

    private function ownedOrder(Request $request, string $order): Order
    {
        return Order::query()
            ->whereBelongsTo($request->user())
            ->where(function ($query) use ($order): void {
                $query->where('order_number', $order)->orWhere('orders.id', is_numeric($order) ? (int) $order : 0);
            })
            ->firstOrFail();
    }

    private function ownedPayment(Request $request, string $payment): Payment
    {
        return Payment::query()
            ->whereKey(is_numeric($payment) ? (int) $payment : 0)
            ->whereHas('order', fn ($query) => $query->whereBelongsTo($request->user()))
            ->firstOrFail();
    }

    private function initiationKey(int $orderId): string
    {
        $key = session($this->sessionKey($orderId));
        if (is_string($key) && $key !== '') {
            return $key;
        }

        $key = (string) Str::uuid();
        session()->put($this->sessionKey($orderId), $key);

        return $key;
    }

    private function sessionKey(int $orderId): string
    {
        return 'storefront_payment_idempotency.'.$orderId;
    }
}

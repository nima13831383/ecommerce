<?php

namespace App\Services\Payments;

use App\Enums\InventoryReservationStatus;
use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Orders\OrderService;
use App\Services\Payments\Data\PaymentVerificationResult;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly OrderService $orders,
    ) {}

    public function initiate(Order $order, string $gatewayAlias, string $idempotencyKey): Payment
    {
        $fingerprint = hash('sha256', json_encode(['gateway' => $gatewayAlias, 'method' => 'online_gateway'], JSON_THROW_ON_ERROR));
        $existing = Payment::query()->where('order_id', $order->id)->where('initiation_idempotency_key', $idempotencyKey)->first();

        if ($existing) {
            return $this->idempotentPayment($existing, $fingerprint);
        }

        try {
            $payment = DB::transaction(function () use ($order, $gatewayAlias, $idempotencyKey, $fingerprint): Payment {
                $order = Order::query()->lockForUpdate()->findOrFail($order->id);
                $this->assertPayable($order);

                return Payment::query()->create([
                    'payment_number' => 'PAY-'.Str::upper((string) Str::ulid()),
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'method' => 'online_gateway',
                    'gateway' => $gatewayAlias,
                    'amount' => $order->grand_total,
                    'paid_amount' => 0,
                    'refunded_amount' => 0,
                    'currency' => $order->currency,
                    'initiation_idempotency_key' => $idempotencyKey,
                    'initiation_fingerprint' => $fingerprint,
                ]);
            });
        } catch (QueryException $exception) {
            $existing = Payment::query()->where('order_id', $order->id)->where('initiation_idempotency_key', $idempotencyKey)->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->idempotentPayment($existing, $fingerprint);
        }

        $result = $this->gateways->gateway($gatewayAlias)->initiate($payment);

        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending) {
                return $payment;
            }

            $this->recordTransaction($payment, 'request', $result->successful, $payment->amount, $result->providerPaymentIdentifier, null, $result->metadata, $result->failureReason);

            if (! $result->successful) {
                $payment->applyStatus(PaymentStatus::Failed, ['failure_reason' => $result->failureReason, 'gateway_response' => $result->metadata]);

                return $payment;
            }

            $payment->applyStatus(PaymentStatus::Processing, ['authority' => $result->providerPaymentIdentifier, 'gateway_response' => $result->metadata]);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($order->status === OrderStatus::Pending) {
                $this->orders->transitionStatus($order, OrderStatus::AwaitingPayment, comment: 'Payment initiated.');
            }

            return $payment;
        });
    }

    public function verify(Payment $payment): Payment
    {
        $payment = Payment::query()->findOrFail($payment->id);

        if ($payment->status === PaymentStatus::Paid) {
            return $payment;
        }

        $result = $this->gateways->gateway((string) $payment->gateway)->verify($payment);

        return DB::transaction(function () use ($payment, $result): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $order = Order::query()->lockForUpdate()->findOrFail($payment->order_id);

            if ($payment->status === PaymentStatus::Paid) {
                return $payment;
            }

            $this->recordTransaction($payment, 'verify', $result->verified, $result->amount ?? 0, $payment->authority, $result->providerReference, $result->metadata, $result->failureReason);

            if (! $result->verified) {
                $payment->applyStatus(PaymentStatus::Failed, ['failure_reason' => $result->failureReason, 'gateway_response' => $result->metadata]);

                return $payment;
            }

            $reconciliation = ! $this->normalSuccessIsPossible($order, $result);
            $payment->applyStatus(PaymentStatus::Paid, [
                'paid_amount' => $result->amount ?? 0,
                'reference_id' => $result->providerReference,
                'gateway_response' => $result->metadata,
                'verified_at' => now(),
                'reconciliation_required' => $reconciliation,
            ]);

            if ($reconciliation) {
                return $payment;
            }

            $this->orders->commitInventoryForOrder($order);
            $order->applyPaymentStatus(OrderPaymentStatus::Paid, $order->grand_total);
            $order->statusHistories()->create(['from_status' => 'unpaid', 'to_status' => 'paid', 'type' => 'payment_status', 'comment' => 'Payment verified.']);
            $this->orders->transitionStatus($order, OrderStatus::Processing, comment: 'Payment verified.');

            return $payment;
        });
    }

    private function assertPayable(Order $order): void
    {
        if ($order->payment_status === OrderPaymentStatus::Paid || $order->status === OrderStatus::Cancelled) {
            throw new DomainException('The order is not payable.');
        }

        if (! $this->hasActiveReservationCoverage($order)) {
            throw new DomainException('The order no longer has active inventory reservation coverage.');
        }
    }

    private function normalSuccessIsPossible(Order $order, PaymentVerificationResult $result): bool
    {
        return $result->amount === $order->grand_total
            && $result->currency === $order->currency
            && $order->payment_status !== OrderPaymentStatus::Paid
            && $order->status !== OrderStatus::Cancelled
            && $this->hasActiveReservationCoverage($order);
    }

    private function hasActiveReservationCoverage(Order $order): bool
    {
        $items = $order->items()->with('inventoryReservation')->get();

        return $items->isNotEmpty() && $items->every(fn ($item) => $item->inventoryReservation?->status === InventoryReservationStatus::Active && $item->inventoryReservation->expires_at->isFuture());
    }

    private function idempotentPayment(Payment $payment, string $fingerprint): Payment
    {
        if (! hash_equals((string) $payment->initiation_fingerprint, $fingerprint)) {
            throw new DomainException('The payment initiation key has already been used with a different request.');
        }

        return $payment;
    }

    private function recordTransaction(Payment $payment, string $type, bool $successful, int $amount, ?string $authority, ?string $reference, array $metadata, ?string $message): void
    {
        $payment->transactions()->create([
            'type' => $type,
            'status' => $successful ? 'success' : 'failed',
            'amount' => $amount,
            'authority' => $authority,
            'reference_id' => $reference,
            'response_payload' => $metadata,
            'message' => $message,
        ]);
    }
}

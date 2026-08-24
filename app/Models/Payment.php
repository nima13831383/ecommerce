<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Payment extends Model
{
    use SoftDeletes;

    private bool $allowsStatusMutation = false;

    protected $fillable = [
        'payment_number',
        'order_id',
        'user_id',
        'method',
        'gateway',
        'status',
        'currency',
        'amount',
        'paid_amount',
        'refunded_amount',
        'authority',
        'reference_id',
        'card_pan',
        'card_hash',
        'gateway_response',
        'failure_reason',
        'paid_at',
        'expires_at',
        'ip_address',
        'initiation_idempotency_key',
        'initiation_fingerprint',
        'reconciliation_required',
        'verified_at',
    ];

    protected $casts = [
        'status' => PaymentStatus::class,
        'amount' => 'integer',
        'paid_amount' => 'integer',
        'refunded_amount' => 'integer',
        'gateway_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'reconciliation_required' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $payment): void {
            if ($payment->isDirty('status') && ! $payment->allowsStatusMutation) {
                throw new LogicException('Payment status changes must use PaymentService.');
            }
        });
    }

    public function applyStatus(PaymentStatus $status, array $attributes = []): void
    {
        $this->allowsStatusMutation = true;

        try {
            $this->forceFill($attributes);
            $this->status = $status;
            $this->save();
        } finally {
            $this->allowsStatusMutation = false;
        }
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}

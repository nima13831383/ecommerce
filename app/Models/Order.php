<?php

namespace App\Models;

use App\Enums\OrderPaymentStatus;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

class Order extends Model
{
    use SoftDeletes;

    private bool $allowsStatusMutation = false;

    private bool $allowsPaymentStatusMutation = false;

    protected $fillable = [
        'order_number',
        'user_id',
        'cart_id',
        'customer_name',
        'customer_mobile',
        'customer_email',
        'national_id',
        'billing_address',
        'shipping_address',
        'currency',
        'items_subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'paid_total',
        'refunded_total',
        'coupon_id',
        'shipping_method_id',
        'tracking_number',
        'ip_address',
        'user_agent',
        'customer_note',
        'admin_note',
        'paid_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'tax_breakdown',
        'idempotency_key',
        'request_fingerprint',
    ];

    protected $casts = [
        'billing_address' => 'array',
        'shipping_address' => 'array',
        'status' => OrderStatus::class,
        'payment_status' => OrderPaymentStatus::class,
        'items_subtotal' => 'integer',
        'discount_total' => 'integer',
        'tax_total' => 'integer',
        'shipping_total' => 'integer',
        'grand_total' => 'integer',
        'paid_total' => 'integer',
        'refunded_total' => 'integer',
        'paid_at' => 'datetime',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'tax_breakdown' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $order): void {
            if ($order->isDirty('status') && ! $order->allowsStatusMutation) {
                throw new LogicException('Order status changes must use OrderService::transitionStatus().');
            }

            if ($order->isDirty('payment_status') && ! $order->allowsPaymentStatusMutation) {
                throw new LogicException('Order payment status changes must use PaymentService.');
            }
        });
    }

    public function applyPaymentStatus(OrderPaymentStatus $status, int $paidTotal): void
    {
        $this->allowsPaymentStatusMutation = true;

        try {
            $this->payment_status = $status;
            $this->paid_total = $paidTotal;
            $this->save();
        } finally {
            $this->allowsPaymentStatusMutation = false;
        }
    }

    public function applyStatus(OrderStatus $status): void
    {
        $this->allowsStatusMutation = true;

        try {
            $this->status = $status;
            $this->save();
        } finally {
            $this->allowsStatusMutation = false;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function inventoryReservations(): HasManyThrough
    {
        return $this->hasManyThrough(
            InventoryReservation::class,
            OrderItem::class,
            'order_id',
            'id',
            'id',
            'inventory_reservation_id',
        );
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function shippingMethod()
    {
        return $this->belongsTo(ShippingMethod::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;


class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'user_id',
        'cart_id',
        'status',
        'payment_status',
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
    ];

    protected $casts = [
        'billing_address'  => 'array',
        'shipping_address' => 'array',
        'items_subtotal'   => 'decimal:0',
        'discount_total'   => 'decimal:0',
        'tax_total'        => 'decimal:0',
        'shipping_total'   => 'decimal:0',
        'grand_total'      => 'decimal:0',
        'paid_total'       => 'decimal:0',
        'refunded_total'   => 'decimal:0',
        'paid_at'          => 'datetime',
        'shipped_at'       => 'datetime',
        'delivered_at'     => 'datetime',
        'cancelled_at'     => 'datetime',
        'tax_breakdown' => 'array',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }
    public function items()
    {
        return $this->hasMany(OrderItem::class);
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

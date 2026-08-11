<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cart extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'token',
        'currency',
        'status',
        'coupon_id',
        'subtotal',
        'discount_total',
        'tax_total',
        'shipping_total',
        'grand_total',
        'notes',
        'last_activity_at',
        'reminder_sent_at',
    ];
    protected $casts = [
        'subtotal'        => 'decimal:0',
        'discount_total'  => 'decimal:0',
        'tax_total'       => 'decimal:0',
        'shipping_total'  => 'decimal:0',
        'grand_total'     => 'decimal:0',
        'last_activity_at'  => 'datetime',
        'reminder_sent_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    // جمع کل سبد بر اساس Snapshot قیمت
    public function getTotalAttribute(): int
    {
        return (int) $this->items->sum(fn($i) => $i->unit_price * $i->quantity);
    }
    // app/Models/Cart.php
    public function coupons()
    {
        return $this->belongsToMany(Coupon::class, 'cart_coupon')
            ->withPivot(['discount_amount', 'sort_order'])
            ->orderByPivot('sort_order');
    }
}

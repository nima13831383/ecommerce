<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'subtotal' => 'integer',
        'discount_total' => 'integer',
        'tax_total' => 'integer',
        'shipping_total' => 'integer',
        'grand_total' => 'integer',
        'last_activity_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
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
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    // app/Models/Cart.php
    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class, 'cart_coupon')
            ->withPivot(['discount_amount', 'sort_order'])
            ->orderByPivot('sort_order');
    }
}

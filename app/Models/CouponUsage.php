<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/CouponUsage.php
class CouponUsage extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'order_id', 'discount_amount'];

    protected $casts = ['discount_amount' => 'decimal:0'];


    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

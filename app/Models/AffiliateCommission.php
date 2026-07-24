<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/AffiliateCommission.php
class AffiliateCommission extends Model
{
    protected $fillable = ['affiliate_id', 'order_id', 'amount', 'status'];

    public function affiliate()
    {
        return $this->belongsTo(Affiliate::class)->withDefault();
    }
    public function order()
    {
        return $this->belongsTo(Order::class)->withDefault();
    }
}

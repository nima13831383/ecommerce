<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Affiliate.php
class Affiliate extends Model
{
    protected $fillable = ['user_id', 'code', 'commission_rate', 'total_earned', 'unpaid_balance', 'status'];
    protected $casts = ['commission_rate' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
    public function commissions()
    {
        return $this->hasMany(AffiliateCommission::class);
    }
}

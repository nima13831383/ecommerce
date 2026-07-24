<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referred_id',
        'code',
        'status',
        'reward_amount',
        'rewarded_at',
    ];

    protected $casts = [
        'reward_amount' => 'decimal:0',
        'rewarded_at'   => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}

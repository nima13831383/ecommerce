<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    protected $fillable = ['referrer_id', 'referred_id', 'code', 'status', 'reward_amount', 'completed_at'];
    protected $casts = ['completed_at' => 'datetime', 'reward_amount' => 'integer'];


    public function referrer()
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }
    public function referred()
    {
        return $this->belongsTo(User::class, 'referred_id');
    }
}

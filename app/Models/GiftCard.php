<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/GiftCard.php
class GiftCard extends Model
{
    protected $fillable = ['code', 'initial_balance', 'balance', 'issued_to', 'redeemed_by', 'status', 'expires_at'];
    protected $casts = ['expires_at' => 'datetime'];

    public function issuedTo()
    {
        return $this->belongsTo(User::class, 'issued_to')->withDefault();
    }
    public function redeemedBy()
    {
        return $this->belongsTo(User::class, 'redeemed_by')->withDefault();
    }
}

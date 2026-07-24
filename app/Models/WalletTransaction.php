<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'balance_after',
        'reason',
        'description',
        'status',
        'reference_type',
        'reference_id',
        'direction',
        'balance_before',
        'reversed_at',
        'meta',

    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'meta'   => 'array',
        'balance_after' => 'decimal:0',
        'balance_before' => 'decimal:0',
        'reversed_at'    => 'datetime',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
    public function reference()
    {
        return $this->morphTo();
    }
}

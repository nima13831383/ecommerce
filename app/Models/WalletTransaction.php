<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model
{
    protected $fillable = [
        'wallet_id',
        'type',
        'amount',
        'fee',
        'status',
        'description',
        'reference_id',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:0',
        'fee'    => 'decimal:0',
        'meta'   => 'array',
    ];

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }
}

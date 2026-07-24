<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'payment_number',
        'order_id',
        'user_id',
        'method',
        'gateway',
        'status',
        'currency',
        'amount',
        'paid_amount',
        'refunded_amount',
        'authority',
        'reference_id',
        'card_pan',
        'card_hash',
        'gateway_response',
        'failure_reason',
        'paid_at',
        'expires_at',
        'ip_address',
    ];

    protected $casts = [
        'amount'           => 'decimal:0',
        'paid_amount'      => 'decimal:0',
        'refunded_amount'  => 'decimal:0',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
        'expires_at'       => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}

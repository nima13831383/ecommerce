<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'type',
        'status',
        'amount',
        'authority',
        'reference_id',
        'gateway_status_code',
        'request_payload',
        'response_payload',
        'message',
    ];

    protected $casts = [
        'amount' => 'integer',
        'request_payload' => 'array',
        'response_payload' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

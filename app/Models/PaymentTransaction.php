<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'payment_id',
        'stage',
        'status',
        'amount',
        'gateway_response',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'gateway_response' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}

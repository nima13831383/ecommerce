<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusHistory extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'from_status',
        'to_status',
        'type',
        'comment',
        'notify_customer',
    ];

    protected $casts = [
        'notify_customer' => 'boolean',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

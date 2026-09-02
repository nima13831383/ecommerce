<?php

namespace App\Models;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationStatus;
use App\Enums\CustomerNotificationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerNotification extends Model
{
    protected $fillable = [
        'user_id', 'order_id', 'type', 'channel', 'recipient_snapshot', 'payload_snapshot',
        'status', 'idempotency_key', 'attempts', 'last_error', 'queued_at', 'sent_at', 'failed_at',
    ];

    protected $casts = [
        'type' => CustomerNotificationType::class,
        'channel' => CustomerNotificationChannel::class,
        'status' => CustomerNotificationStatus::class,
        'recipient_snapshot' => 'array',
        'payload_snapshot' => 'array',
        'attempts' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php
// app/Models/RewardPoint.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardPoint extends Model
{
    protected $fillable = [
        'user_id',
        'order_id',
        'direction',
        'points',
        'balance_after',
        'reason',
        'expires_at',
    ];

    protected $casts = [
        'points'        => 'integer',
        'balance_after' => 'integer',
        'expires_at'    => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class)->withDefault();
    }

    // اسکوپ‌های کمکی برای گزارش‌گیری
    public function scopeEarned($q)
    {
        return $q->where('direction', 'earn');
    }
    public function scopeRedeemed($q)
    {
        return $q->where('direction', 'redeem');
    }
}

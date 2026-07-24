<?php
// app/Models/ProductAnswer.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAnswer extends Model
{
    protected $fillable = [
        'question_id',
        'user_id',
        'author_name',
        'is_staff',
        'body',
        'status',
        'helpful_count',
    ];

    protected $casts = [
        'is_staff'      => 'boolean',
        'helpful_count' => 'integer',
    ];
    public function question(): BelongsTo
    {
        return $this->belongsTo(ProductQuestion::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    public function getDisplayNameAttribute(): ?string
    {
        return $this->user?->exists ? $this->user->name : $this->author_name;
    }
}

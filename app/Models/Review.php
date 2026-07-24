<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Review.php
//for products
class Review extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'author_name',
        'rating',
        'title',
        'body',
        'verified_purchase',
        'status',
        'helpful_count',
    ];

    protected $casts = [
        'rating'            => 'integer',
        'verified_purchase' => 'boolean',
        'helpful_count'     => 'integer',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function votes()
    {
        return $this->hasMany(ReviewVote::class);
    }
    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }
    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }

    // نام نمایشی: کاربر ثبت‌شده یا مهمان
    public function getDisplayNameAttribute(): ?string
    {
        return $this->user?->exists ? $this->user->name : $this->author_name;
    }
}

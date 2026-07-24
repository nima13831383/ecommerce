<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// app/Models/ReviewVote.php
class ReviewVote extends Model
{
    protected $fillable = ['review_id', 'user_id', 'vote'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function scopeHelpful($q)
    {
        return $q->where('vote', 'helpful');
    }
    public function scopeNotHelpful($q)
    {
        return $q->where('vote', 'not_helpful');
    }
}

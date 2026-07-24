<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Newsletter.php
class Newsletter extends Model
{
    protected $fillable = ['email', 'user_id', 'status', 'token', 'confirmed_at', 'unsubscribed_at'];
    protected $casts = ['confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
}

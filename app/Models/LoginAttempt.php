<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/LoginAttempt.php
class LoginAttempt extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'identifier', 'ip_address', 'user_agent', 'successful', 'attempted_at'];
    protected $casts = ['successful' => 'boolean', 'attempted_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
}

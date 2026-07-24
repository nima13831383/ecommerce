<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/ActivityLog.php
class ActivityLog extends Model
{
    protected $fillable = ['user_id', 'action', 'subject_type', 'subject_id', 'ip_address', 'user_agent', 'properties'];
    protected $casts = ['properties' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
    public function subject()
    {
        return $this->morphTo();
    }
}

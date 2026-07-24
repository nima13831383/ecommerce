<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Campaign.php
class Campaign extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'description', 'payload', 'is_active', 'starts_at', 'ends_at'];
    protected $casts = [
        'payload'    => 'array',
        'is_active'  => 'boolean',
        'starts_at'  => 'datetime',
        'ends_at'    => 'datetime',
    ];
}

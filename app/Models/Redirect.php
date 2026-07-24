<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Redirect.php
class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_active', 'hits', 'last_hit_at'];
    protected $casts = ['is_active' => 'boolean', 'last_hit_at' => 'datetime'];
}

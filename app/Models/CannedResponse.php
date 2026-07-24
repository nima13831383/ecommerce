<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/CannedResponse.php
class CannedResponse extends Model
{
    protected $fillable = ['department_id', 'title', 'shortcut', 'body', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(TicketDepartment::class)->withDefault();
    }
}

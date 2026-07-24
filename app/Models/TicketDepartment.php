<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/TicketDepartment.php
class TicketDepartment extends Model
{
    protected $fillable = ['name', 'slug', 'is_active'];
    protected $casts = ['is_active' => 'boolean'];

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'department_id');
    }
    public function cannedResponses()
    {
        return $this->hasMany(CannedResponse::class, 'department_id');
    }
}

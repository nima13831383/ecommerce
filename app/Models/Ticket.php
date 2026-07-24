<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'department_id',
        'order_id',
        'assigned_to',
        'subject',
        'priority',
        'status',
        'last_reply_at',
        'closed_at',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'closed_at'     => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function department()
    {
        return $this->belongsTo(TicketDepartment::class);
    }
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
    public function messages()
    {
        return $this->hasMany(TicketMessage::class);
    }
}

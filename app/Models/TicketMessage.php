<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/TicketMessage.php
class TicketMessage extends Model
{
    protected $fillable = ['ticket_id', 'user_id', 'is_staff', 'body', 'is_internal_note'];
    protected $casts = ['is_staff' => 'boolean', 'is_internal_note' => 'boolean'];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class)->withDefault();
    }
    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/TicketAttachment.php
class TicketAttachment extends Model
{
    protected $fillable = ['ticket_message_id', 'file_path', 'original_name', 'mime_type', 'size'];
    protected $casts = ['size' => 'integer'];

    public function message()
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }
}

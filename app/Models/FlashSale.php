<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/FlashSale.php
class FlashSale extends Model
{
    protected $fillable = ['title', 'starts_at', 'ends_at', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(FlashSaleItem::class);
    }
}

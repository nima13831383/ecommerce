<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Tag.php
class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_tag');
    }
}

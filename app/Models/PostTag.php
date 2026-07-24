<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// app/Models/PostTag.php
class PostTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'post_tag_id', 'post_id');
    }
    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}

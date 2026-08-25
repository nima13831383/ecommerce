<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/PostTag.php
class PostTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $tag): void {
            $tag->slug = Post::normalizeSlug($tag->slug ?: $tag->name);
        });
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tag', 'post_tag_id', 'post_id');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}

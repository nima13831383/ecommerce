<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/PostCategory.php
class PostCategory extends Model
{
    protected $fillable = ['parent_id', 'name', 'slug', 'description'];

    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            $category->slug = Post::normalizeSlug($category->slug ?: $category->name);
        });
    }

    public function parent()
    {
        return $this->belongsTo(PostCategory::class, 'parent_id')->withDefault();
    }

    public function children()
    {
        return $this->hasMany(PostCategory::class, 'parent_id');
    }

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_post_category');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}

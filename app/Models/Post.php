<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = ['author_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'status', 'views', 'published_at'];

    protected $casts = [
        'views'        => 'integer',
        'published_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }
    public function categories()
    {
        return $this->belongsToMany(PostCategory::class, 'post_category_pivot');
    }
    public function tags()
    {
        return $this->belongsToMany(PostTag::class, 'post_tag', 'post_id', 'post_tag_id');
    }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'post_product');
    }
    public function postTags()
    {
        return $this->belongsToMany(PostTag::class, 'post_tag', 'post_id', 'post_tag_id');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}

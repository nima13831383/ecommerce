<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image',
        'icon',
        'meta_title',
        'meta_description',
        'sort_order',
        'is_active',
        'is_featured',
        'is_hidden',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'is_hidden'   => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }
    // public function products()
    // {
    //     return $this->belongsToMany(Product::class, 'product_categories');
    // }
    public function products()
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }


    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
// app/Models/Tag.php
class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_tag');
    }
    protected static function booted(): void
    {
        static::saving(function (self $tag) {
            if (blank($tag->slug)) {
                $tag->slug = Str::slug($tag->name, language: null) ?: (string) Str::uuid();
            }
        });
    }



    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, fn(Builder $q) => $q
            ->where('name', 'like', "%{$term}%")
            ->orWhere('slug', 'like', "%{$term}%"));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

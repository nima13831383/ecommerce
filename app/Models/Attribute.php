<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'is_variation',
        'is_visible',
        'sort_order',
    ];

    protected $casts = [
        'is_variation' => 'boolean',
        'is_visible'   => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function values()
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order');
    }

    // اسکوپ‌ها
    public function scopeVariation($query)
    {
        return $query->where('is_variation', true);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }
}

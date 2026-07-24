<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/SeoMeta.php
class SeoMeta extends Model
{
    protected $fillable = [
        'meta_title',
        'meta_description',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
        'no_index',
        'no_follow',
        'schema_markup',
    ];
    protected $casts = [
        'no_index'      => 'boolean',
        'no_follow'     => 'boolean',
        'schema_markup' => 'array',
    ];

    public function seoable()
    {
        return $this->morphTo();
    }
}

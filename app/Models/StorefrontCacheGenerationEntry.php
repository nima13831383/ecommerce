<?php

namespace App\Models;

use App\Enums\CacheRebuildDomain;
use Illuminate\Database\Eloquent\Model;

class StorefrontCacheGenerationEntry extends Model
{
    protected $fillable = [
        'domain',
        'backend',
        'generation',
        'cache_key',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'domain' => CacheRebuildDomain::class,
            'generation' => 'integer',
            'expires_at' => 'datetime',
        ];
    }
}

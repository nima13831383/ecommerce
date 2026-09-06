<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StorefrontCacheGenerationPrune extends Model
{
    protected $fillable = [
        'backend',
        'started_at',
        'finished_at',
        'status',
        'deleted_entries',
        'deleted_runs',
        'error_summary',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'deleted_entries' => 'integer',
            'deleted_runs' => 'integer',
        ];
    }
}

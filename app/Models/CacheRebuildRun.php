<?php

namespace App\Models;

use App\Enums\CacheRebuildDomain;
use App\Enums\CacheRebuildStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacheRebuildRun extends Model
{
    protected $fillable = [
        'domain',
        'requested_by',
        'requested_at',
        'status',
        'started_at',
        'finished_at',
        'error_summary',
        'target_backend',
        'target_generation',
    ];

    protected function casts(): array
    {
        return [
            'domain' => CacheRebuildDomain::class,
            'status' => CacheRebuildStatus::class,
            'requested_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'target_generation' => 'integer',
        ];
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AuthOtpChallenge extends Model
{
    use HasUlids;

    public const PURPOSE_LOGIN = 'login';

    public const PURPOSE_REGISTER = 'register';

    protected $fillable = [
        'mobile',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'max_attempts',
        'sent_at',
        'consumed_at',
        'invalidated_at',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function scopeUsable($query): void
    {
        $query
            ->whereNull('consumed_at')
            ->whereNull('invalidated_at')
            ->where('expires_at', '>', now())
            ->whereColumn('attempts', '<', 'max_attempts');
    }
}

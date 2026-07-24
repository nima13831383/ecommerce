<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'description', 'regions', 'country_code', 'state_codes'];

    protected $casts = [
        'regions'     => 'array',
        'state_codes' => 'array',
    ];

    public function methods()
    {
        return $this->hasMany(ShippingMethod::class);
    }
    public function rates(): HasMany
    {
        return $this->hasMany(ShippingRate::class);
    }
}

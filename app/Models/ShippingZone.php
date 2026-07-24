<?php
// app/Models/ShippingZone.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShippingZone extends Model
{
    protected $fillable = ['name', 'slug', 'match_type', 'regions', 'priority', 'is_active'];

    protected $casts = [
        'regions'   => 'array',
        'is_active' => 'boolean',
    ];
    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
    public function rates()
    {
        return $this->hasMany(ShippingRate::class);
    }
}

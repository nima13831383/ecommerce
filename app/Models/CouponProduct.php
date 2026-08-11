<?php
// app/Models/CouponProduct.php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CouponProduct extends Pivot
{
    protected $casts = ['is_excluded' => 'boolean'];
}

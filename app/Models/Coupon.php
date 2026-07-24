<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// app/Models/Coupon.php
class Coupon extends Model
{
    protected $fillable = [
        'code',
        'description',
        'type',
        'amount',
        'free_shipping',
        'min_spend',
        'max_spend',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'usage_count',
        'individual_use_only',
        'exclude_sale_items',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'amount'               => 'decimal:0',
        'min_spend'            => 'decimal:0',
        'max_spend'            => 'decimal:0',
        'max_discount'         => 'decimal:0',
        'free_shipping'        => 'boolean',
        'individual_use_only'  => 'boolean',
        'exclude_sale_items'   => 'boolean',
        'is_active'            => 'boolean',
        'starts_at'            => 'datetime',
        'expires_at'           => 'datetime',
    ];

    public function usages()
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function includedProducts()
    {
        return $this->belongsToMany(Product::class, 'coupon_product')
            ->wherePivot('is_excluded', false);
    }

    public function excludedProducts()
    {
        return $this->belongsToMany(Product::class, 'coupon_product')
            ->wherePivot('is_excluded', true);
    }

    public function includedCategories()
    {
        return $this->belongsToMany(Category::class, 'coupon_category')
            ->wherePivot('is_excluded', false);
    }

    public function excludedCategories()
    {
        return $this->belongsToMany(Category::class, 'coupon_category')
            ->wherePivot('is_excluded', true);
    }

    // اسکوپ کوپن‌های معتبر در بازه زمانی و فعال
    public function scopeUsable($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(fn($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now));
    }

    public function hasReachedLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    public function userUsageCount(int $userId): int
    {
        return $this->usages()->where('user_id', $userId)->count();
    }
}

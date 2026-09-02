<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'description',
        'type',
        'amount',
        'min_spend',
        'max_spend',
        'max_discount',
        'usage_limit',
        'usage_limit_per_user',
        'usage_count',
        'individual_use_only',
        'exclude_discounted_products',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'min_spend' => 'integer',
            'max_spend' => 'integer',
            'max_discount' => 'integer',
            'individual_use_only' => 'boolean',
            'exclude_discounted_products' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'usage_limit' => 'integer',
            'usage_limit_per_user' => 'integer',
            'usage_count' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $coupon): void {
            $coupon->code = self::normalizeCode((string) $coupon->code);
        });
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    public function isAvailableAt(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->is_active
            && ($this->starts_at === null || $this->starts_at->lessThanOrEqualTo($now))
            && ($this->expires_at === null || $this->expires_at->greaterThanOrEqualTo($now))
            && ! $this->hasReachedLimit();
    }

    /* ================= روابط پایه ================= */

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product')
            ->withPivot('is_excluded');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'coupon_category')
            ->withPivot('is_excluded');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'coupon_user')
            ->withPivot('is_excluded');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(config('permission.models.role'), 'coupon_role')
            ->withPivot('is_excluded');
    }

    public function includedRoles(): BelongsToMany
    {
        return $this->roles()->wherePivot('is_excluded', false);
    }

    public function excludedRoles(): BelongsToMany
    {
        return $this->roles()->wherePivot('is_excluded', true);
    }

    /* ================= میان‌برهای شامل/استثنا ================= */

    public function includedProducts(): BelongsToMany
    {
        return $this->products()->wherePivot('is_excluded', false);
    }

    public function excludedProducts(): BelongsToMany
    {
        return $this->products()->wherePivot('is_excluded', true);
    }

    public function includedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_excluded', false);
    }

    public function excludedUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('is_excluded', true);
    }

    /* ================= هستهٔ منطق «شامل/استثنا» ================= */

    /**
     * قاعدهٔ عمومی (همان رفتار ووکامرس):
     *  - included خالی و excluded خالی  → همه مجازند
     *  - included پر                    → فقط اعضای included (excluded نادیده گرفته نمی‌شود؛ برای امنیت باز هم چک می‌شود)
     *  - included خالی و excluded پر    → همه به‌جز excluded
     */
    protected function matchesRule(array $includedIds, array $excludedIds, array $candidateIds): bool
    {
        if ($includedIds !== []) {
            // باید حداقل یکی از موارد انتخابی در لیست شامل باشد
            return array_intersect($candidateIds, $includedIds) !== [];
        }

        if ($excludedIds !== []) {
            // همه به‌جز موارد استثنا؛ حداقل یک مورد خارج از استثنا لازم است
            return array_diff($candidateIds, $excludedIds) !== [];
        }

        return true; // هیچ محدودیتی تعریف نشده
    }

    /* ---------- محصولات ---------- */

    public function appliesToProduct(Product $product): bool
    {
        $included = $this->includedProducts->pluck('id')->all();
        $excluded = $this->excludedProducts->pluck('id')->all();

        // 1) اگر محصول صراحتاً استثنا شده → رد
        if (in_array($product->id, $excluded, true)) {
            return false;
        }

        // 2) اگر لیست شامل پر است → باید داخلش باشد
        if ($included !== [] && ! in_array($product->id, $included, true)) {
            return false;
        }

        return true;
    }

    /* ---------- کاربران ---------- */

    public function appliesToUser(?User $user): bool
    {
        $included = $this->includedUsers->pluck('id')->all();
        $excluded = $this->excludedUsers->pluck('id')->all();

        if ($included === [] && $excluded === []) {
            return true;               // عمومی
        }

        if ($user === null) {
            return false;              // کوپن محدود است ولی کاربر مهمان است
        }

        if (in_array($user->id, $excluded, true)) {
            return false;
        }

        if ($included !== [] && ! in_array($user->id, $included, true)) {
            return false;
        }

        return true;
    }

    /** فیلتر کردن مجموعهٔ محصولات سبد به آن‌هایی که کوپن رویشان اعمال می‌شود */
    public function filterEligibleProducts(Collection $products): Collection
    {
        return $products->filter(fn (Product $p) => $this->appliesToProduct($p))->values();
    }

    /* ================= اسکوپ‌ها ================= */

    public function scopeUsable(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
            ->where(fn (Builder $q) => $q->whereNull('usage_limit')
                ->orWhereColumn('usage_count', '<', 'usage_limit'));
    }

    /**
     * کوپن‌های در دسترس یک کاربر با در نظر گرفتن included/excluded users.
     */
    public function scopeAvailableFor(Builder $query, ?int $userId): Builder
    {
        return $query
            // کاربر نباید در لیست استثنا باشد
            ->when($userId !== null, fn (Builder $q) => $q->whereDoesntHave(
                'users',
                fn (Builder $u) => $u->whereKey($userId)->wherePivot('is_excluded', true)
            ))
            // یا لیست شامل خالی است، یا کاربر در آن هست
            ->where(function (Builder $q) use ($userId) {
                $q->whereDoesntHave('users', fn (Builder $u) => $u->wherePivot('is_excluded', false));

                if ($userId !== null) {
                    $q->orWhereHas(
                        'users',
                        fn (Builder $u) => $u->whereKey($userId)->wherePivot('is_excluded', false)
                    );
                }
            });
    }

    /* ================= کمک‌متدها ================= */

    public function hasReachedLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }

    public function userUsageCount(int $userId): int
    {
        return $this->usages()->where('user_id', $userId)->count();
    }

    public function hasUserReachedLimit(int $userId): bool
    {
        return $this->usage_limit_per_user !== null
            && $this->userUsageCount($userId) >= $this->usage_limit_per_user;
    }

    public function isRestrictedToUsers(): bool
    {
        return $this->includedUsers()->exists();
    }
}

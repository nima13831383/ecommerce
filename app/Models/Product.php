<?php

namespace App\Models;

use App\Enums\InventoryReservationStatus;
use App\Services\Settings\SettingsService;
use App\Services\Tax\TaxCalculator;
use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

// app/Models/Product.php
class Product extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (self $product): void {
            if (! $product->isForceDeleting()) {
                return;
            }

            $variationIds = $product->variations()->pluck('id');
            $owners = [
                [self::class, $product->getKey()],
                ...$variationIds->map(fn (int $id): array => [ProductVariation::class, $id]),
            ];

            $hasActiveReservation = collect($owners)->contains(fn (array $owner): bool => InventoryReservation::query()
                ->where('inventory_owner_type', $owner[0])
                ->where('inventory_owner_id', $owner[1])
                ->where('status', InventoryReservationStatus::Active)
                ->exists());

            if ($hasActiveReservation) {
                throw new DomainException('Products with active inventory reservations cannot be permanently deleted.');
            }

            $product->images()->each(fn (ProductImage $image) => Storage::disk(ProductImage::storageDisk())->delete($image->path));
        });
    }

    protected $fillable = [
        'brand_id',
        'type',
        'name',
        'slug',
        'sku',
        'short_description',
        'description',
        'price',
        'sale_price',
        'sale_starts_at',
        'sale_ends_at',
        'manage_stock',
        'low_stock_threshold',
        'is_downloadable',
        'is_virtual',
        'weight',
        'volume',
        'parcel_type',
        'length',
        'width',
        'height',
        'tax_class_id',
        'shipping_class_id',
        'external_url',
        'button_text',
        'views_count',
        'sales_count',
        'rating_avg',
        'rating_count',
        'meta_title',
        'meta_description',
        'status',
        'is_featured',
        'published_at',
        'external_url',
        'button_text',
        'download_limit',
        'download_expiry',
        // 'variation_attributes', // ← اضافه شد تا JSON ذخیره شود
    ];

    protected $casts = [
        'price' => 'decimal:0',
        'sale_price' => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at' => 'datetime',
        'manage_stock' => 'boolean',
        'is_downloadable' => 'boolean',
        'is_virtual' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
        'weight' => 'decimal:2',
        'volume' => 'decimal:6',
        'parcel_type' => 'string',
        'rating_avg' => 'decimal:2',
        // 'variation_attributes' => 'array',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    // public function variations()
    // {
    //     return $this->hasMany(ProductVariation::class);
    // }
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function questions()
    {
        return $this->hasMany(ProductQuestion::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'product_tag');
    }

    // public function attributes()
    // {
    //     return $this->belongsToMany(Attribute::class, 'attribute_product')
    //         ->withPivot('is_variation', 'is_visible', 'sort_order');
    // }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage()
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true);
    }
    // app/Models/Product.php
    // public function attributes()
    // {
    //     return $this->belongsToMany(Attribute::class, 'attribute_product')
    //         ->withPivot(['is_variation', 'is_visible', 'sort_order']);
    // }

    // public function variations()
    // {
    //     return $this->hasMany(ProductVariation::class);
    // }

    // public function groupedChildren()
    // {
    //     return $this->belongsToMany(
    //         Product::class,
    //         'grouped_products',
    //         'parent_id',
    //         'child_id'
    //     );
    // }
    public function downloads(): HasMany
    {
        return $this->hasMany(ProductDownload::class);
    }

    // محصولات فرزند (برای grouped)
    public function groupedChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'grouped_products',
            'parent_id',
            'child_id'
        )
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');
    }

    // این محصول عضو کدام گروه‌هاست (معکوس)
    public function groupedParents(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'grouped_products',
            'child_id',
            'parent_id'
        )->withTimestamps();
    }

    public function downloadablePermissions(): HasMany
    {
        return $this->hasMany(DownloadablePermission::class);
    }

    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    // public function variations(): HasMany
    // {
    //     return $this->hasMany(ProductVariation::class);
    // }
    // public function attributeValues(): BelongsToMany
    // {
    //     return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    // }

    /** @return HasMany<ProductVariation, $this> */
    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'attribute_value_product');
    }

    public function coupons(): BelongsToMany
    {
        return $this->belongsToMany(Coupon::class)
            ->withPivot('is_excluded');
    }

    // app/Models/Product.php
    public function taxClass(): BelongsTo
    {
        return $this->belongsTo(TaxClass::class);
    }

    public function getEffectiveTaxClass(): ?TaxClass
    {
        if ($this->taxClass?->is_active) {
            return $this->taxClass;
        }

        $id = app(SettingsService::class)->get('default_tax_class_id');

        return $id ? TaxClass::query()->whereKey((int) $id)->where('is_active', true)->first() : null;
    }

    public function taxAmountForPrice(int $taxableAmountRials, int $quantity = 1): int
    {
        return app(TaxCalculator::class)->calculateTax(
            taxableAmountRials: $taxableAmountRials,
            taxClass: $this->getEffectiveTaxClass(),
            quantity: $quantity,
        );
    }
}

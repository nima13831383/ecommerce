<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


// app/Models/Product.php
class Product extends Model
{
    use SoftDeletes;

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
        'stock_quantity',
        'stock_status',
        'low_stock_threshold',
        'is_downloadable',
        'is_virtual',
        'weight',
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
        'variation_attributes', // ← اضافه شد تا JSON ذخیره شود
    ];

    protected $casts = [
        'price'          => 'decimal:0',
        'sale_price'     => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'manage_stock'   => 'boolean',
        'is_downloadable' => 'boolean',
        'is_virtual'     => 'boolean',
        'is_featured'    => 'boolean',
        'published_at'   => 'datetime',
        'weight'         => 'decimal:2',
        'rating_avg'     => 'decimal:2',
        'variation_attributes' => 'array',
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
    public function taxClass()
    {
        return $this->belongsTo(TaxClass::class)->withDefault();
    }




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
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class, 'attribute_product')
            ->withPivot(['is_variation', 'is_visible', 'sort_order']);
    }

    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }

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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'meta_title',
        'meta_description',
        'status',
        'is_featured',
        'published_at',
    ];

    protected $casts = [
        'price'          => 'decimal:0',
        'sale_price'     => 'decimal:0',
        'sale_starts_at' => 'datetime',
        'sale_ends_at'   => 'datetime',
        'published_at'   => 'datetime',
        'manage_stock'   => 'boolean',
        'is_downloadable' => 'boolean',
        'is_virtual'     => 'boolean',
        'is_featured'    => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
    public function variations()
    {
        return $this->hasMany(ProductVariation::class);
    }
    public function images()
    {
        return $this->hasMany(ProductImage::class);
    }
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
    public function questions()
    {
        return $this->hasMany(ProductQuestion::class);
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
    public function attributes()
    {
        return $this->belongsToMany(Attribute::class)->withPivot('values');
    }
    public function taxClass()
    {
        return $this->belongsTo(TaxClass::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'alt', 'is_primary', 'sort_order'];

    protected $casts = ['is_primary' => 'boolean'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function storageDisk(): string
    {
        return (string) config('media.public_disk', 'public');
    }

    protected static function booted(): void
    {
        static::saving(function (self $image): void {
            if (! $image->is_primary) {
                return;
            }

            self::query()
                ->where('product_id', $image->product_id)
                ->when($image->exists, fn ($query) => $query->whereKeyNot($image->getKey()))
                ->where('is_primary', true)
                ->update(['is_primary' => false]);
        });

        static::deleted(function (self $image): void {
            Storage::disk(self::storageDisk())->delete($image->path);
        });
    }
}

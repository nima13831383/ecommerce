<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class Post extends Model
{
    use SoftDeletes;

    protected $fillable = ['author_id', 'title', 'slug', 'excerpt', 'content', 'featured_image', 'status', 'views', 'published_at'];

    protected $casts = [
        'views' => 'integer',
        'status' => PostStatus::class,
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $post): void {
            if (blank($post->slug)) {
                $post->slug = self::normalizeSlug($post->title);
            } else {
                $post->slug = self::normalizeSlug($post->slug);
            }

            $status = $post->status instanceof PostStatus ? $post->status : PostStatus::tryFrom((string) $post->status);
            if ($status === PostStatus::Draft) {
                $post->published_at = null;
            }

            if ($status === PostStatus::Scheduled && (! $post->published_at || $post->published_at->lessThanOrEqualTo(now()))) {
                throw ValidationException::withMessages(['published_at' => 'نوشته زمان‌بندی‌شده باید زمان انتشار آینده داشته باشد.']);
            }

            if ($status === PostStatus::Published && (! $post->published_at || $post->published_at->isFuture())) {
                throw ValidationException::withMessages(['published_at' => 'نوشته منتشرشده باید زمان انتشار معتبر داشته باشد.']);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id')->withTrashed();
    }

    public function categories()
    {
        return $this->belongsToMany(PostCategory::class, 'post_post_category', 'post_id', 'post_category_id');
    }

    public function tags()
    {
        return $this->belongsToMany(PostTag::class, 'post_tag', 'post_id', 'post_tag_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'post_product');
    }

    public function postTags()
    {
        return $this->belongsToMany(PostTag::class, 'post_tag', 'post_id', 'post_tag_id');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    /** @param Builder<Post> $query */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', PostStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public static function normalizeSlug(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^\pL\pN_-]+/u', '-', $value) ?? '';
        $value = preg_replace('/-+/', '-', $value) ?? '';

        return trim(Str::lower($value), '-_');
    }
}

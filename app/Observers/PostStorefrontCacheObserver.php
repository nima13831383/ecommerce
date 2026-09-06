<?php

namespace App\Observers;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Services\Storefront\StorefrontDetailCacheRefreshService;

class PostStorefrontCacheObserver
{
    public bool $afterCommit = true;

    public function __construct(private readonly StorefrontDetailCacheRefreshService $refreshes) {}

    public function updated(Post $post): void
    {
        $previous = $post->storefrontCachePrevious ?? $post->getPrevious();
        $post->storefrontCachePrevious = null;

        $this->refreshes->requestPost(
            $post->getKey(),
            $previous['slug'] ?? $post->slug,
            $this->wasPublic($post, $previous),
        );
    }

    public function deleted(Post $post): void
    {
        $this->refreshes->requestPost($post->getKey(), $post->slug, true);
    }

    public function restored(Post $post): void
    {
        $this->refreshes->requestPost($post->getKey(), $post->slug, false);
    }

    private function wasPublic(Post $post, array $previous): bool
    {
        $publishedAt = $previous['published_at'] ?? $post->published_at;

        $status = $previous['status'] ?? $post->status;

        return ($status instanceof PostStatus ? $status : PostStatus::tryFrom((string) $status)) === PostStatus::Published
            && $publishedAt !== null
            && now()->greaterThanOrEqualTo($publishedAt);
    }
}

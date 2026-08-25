<?php

namespace App\Services\Blog;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class PostService
{
    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $actor = null): Post
    {
        $data['status'] = PostStatus::Draft;
        $data['published_at'] = null;
        $data['author_id'] ??= $actor?->getKey();

        $this->validateAuthor($data['author_id'] ?? null);

        return DB::transaction(function () use ($data): Post {
            $post = Post::query()->create($this->editableData($data));
            $post->categories()->sync($data['categories'] ?? []);
            $post->postTags()->sync($data['postTags'] ?? []);
            $this->syncSeoMeta($post, $data['seo_meta'] ?? null);

            return $post->load(['author', 'categories', 'postTags']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Post $post, array $data, ?User $actor = null): Post
    {
        if (array_key_exists('author_id', $data)) {
            $this->validateAuthor($data['author_id']);
        }

        return DB::transaction(function () use ($post, $data): Post {
            $post->fill($this->editableData($data));
            $post->save();

            if (array_key_exists('categories', $data)) {
                $post->categories()->sync($data['categories'] ?? []);
            }

            if (array_key_exists('postTags', $data)) {
                $post->postTags()->sync($data['postTags'] ?? []);
            }

            $this->syncSeoMeta($post, $data['seo_meta'] ?? null);

            return $post->load(['author', 'categories', 'postTags']);
        });
    }

    public function publish(Post $post, ?User $actor = null): Post
    {
        return DB::transaction(function () use ($post, $actor): Post {
            $post = Post::query()->lockForUpdate()->findOrFail($post->getKey());
            $post->status = PostStatus::Published;
            $post->published_at = now();
            $post->save();
            $this->logTransition($post, PostStatus::Published, $actor);

            return $post->refresh();
        });
    }

    public function schedule(Post $post, CarbonInterface $publishedAt, ?User $actor = null): Post
    {
        if ($publishedAt->lessThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['published_at' => 'زمان انتشار باید در آینده باشد.']);
        }

        return DB::transaction(function () use ($post, $publishedAt, $actor): Post {
            $post = Post::query()->lockForUpdate()->findOrFail($post->getKey());
            $post->status = PostStatus::Scheduled;
            $post->published_at = $publishedAt;
            $post->save();
            $this->logTransition($post, PostStatus::Scheduled, $actor);

            return $post->refresh();
        });
    }

    public function unpublish(Post $post, ?User $actor = null): Post
    {
        return DB::transaction(function () use ($post, $actor): Post {
            $post = Post::query()->lockForUpdate()->findOrFail($post->getKey());
            $post->status = PostStatus::Draft;
            $post->published_at = null;
            $post->save();
            $this->logTransition($post, PostStatus::Draft, $actor);

            return $post->refresh();
        });
    }

    /** @param Builder<Post> $query */
    public function applyPublishedScope(Builder $query): Builder
    {
        return $query->published();
    }

    private function validateAuthor(?int $authorId): void
    {
        if ($authorId !== null && ! User::withTrashed()->whereKey($authorId)->exists()) {
            throw ValidationException::withMessages(['author_id' => 'نویسنده انتخاب‌شده وجود ندارد.']);
        }
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function editableData(array $data): array
    {
        return array_intersect_key($data, array_flip([
            'author_id', 'title', 'slug', 'excerpt', 'content', 'featured_image',
        ]));
    }

    /** @param array<string, mixed>|null $data */
    private function syncSeoMeta(Post $post, ?array $data): void
    {
        if ($data === null) {
            return;
        }

        $post->seoMeta()->updateOrCreate([], Arr::only($data, [
            'meta_title', 'meta_description', 'canonical_url', 'og_title', 'og_description',
            'og_image', 'no_index', 'no_follow', 'schema_markup',
        ]));
    }

    private function logTransition(Post $post, PostStatus $to, ?User $actor): void
    {
        Log::info('blog.post_status_changed', [
            'post_id' => $post->getKey(),
            'to_status' => $to->value,
            'actor_user_id' => $actor?->getKey() ?? auth()->id(),
        ]);
    }
}

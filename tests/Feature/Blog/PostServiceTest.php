<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\User;
use App\Services\Blog\PostService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

function blogAuthor(): User
{
    return User::factory()->create();
}

function blogPostData(User $author, array $overrides = []): array
{
    return array_replace([
        'author_id' => $author->id,
        'title' => 'نوشته آزمایشی',
        'content' => '<p>محتوای آزمایشی</p>',
    ], $overrides);
}

test('posts generate stable URL-safe slugs and published scope excludes drafts and future posts', function (): void {
    Carbon::setTestNow('2026-08-25 12:00:00');
    $author = blogAuthor();
    $service = app(PostService::class);

    $draft = $service->create(blogPostData($author, ['title' => 'عنوان فارسی']));
    $published = Post::query()->create(blogPostData($author, [
        'title' => 'Published', 'slug' => 'published', 'status' => PostStatus::Published, 'published_at' => now(),
    ]));
    $future = Post::query()->create(blogPostData($author, [
        'title' => 'Future', 'slug' => 'future', 'status' => PostStatus::Scheduled, 'published_at' => now()->addMinute(),
    ]));

    expect($draft->slug)->toBe('عنوان-فارسی')
        ->and(Post::published()->pluck('id')->all())->toBe([$published->id]);

    Carbon::setTestNow();
    expect($future->fresh()->status)->toBe(PostStatus::Scheduled);
});

test('scheduled publishing requires a future time and becomes visible at its boundary', function (): void {
    Carbon::setTestNow('2026-08-25 12:00:00');
    $author = blogAuthor();
    $post = app(PostService::class)->create(blogPostData($author));

    expect(fn () => app(PostService::class)->schedule($post, now()))
        ->toThrow(ValidationException::class);

    app(PostService::class)->schedule($post, now()->addHour());
    expect($post->fresh()->status)->toBe(PostStatus::Scheduled)
        ->and(Post::published()->whereKey($post)->exists())->toBeFalse();

    Carbon::setTestNow('2026-08-25 13:00:00');
    expect(Post::published()->whereKey($post)->exists())->toBeFalse();

    app(PostService::class)->publish($post);
    expect(Post::published()->whereKey($post)->exists())->toBeTrue();
    Carbon::setTestNow();
});

test('slug remains stable when a title changes and duplicate slugs are rejected', function (): void {
    $author = blogAuthor();
    $post = app(PostService::class)->create(blogPostData($author, ['slug' => 'stable-slug']));
    app(PostService::class)->update($post, ['title' => 'عنوان جدید']);

    expect($post->fresh()->slug)->toBe('stable-slug');

    expect(fn () => Post::query()->create(blogPostData($author, ['slug' => 'stable-slug'])))
        ->toThrow(QueryException::class);
});

test('post categories, tags, and soft-deleted authors remain usable', function (): void {
    $author = blogAuthor();
    $category = PostCategory::query()->create(['name' => 'دسته آزمایشی']);
    $tag = PostTag::query()->create(['name' => 'برچسب آزمایشی']);
    $post = app(PostService::class)->create(blogPostData($author, [
        'categories' => [$category->id], 'postTags' => [$tag->id],
    ]));
    $author->delete();

    expect($post->fresh()->author->is($author))->toBeTrue()
        ->and($post->fresh()->categories->contains($category))->toBeTrue()
        ->and($post->fresh()->postTags->contains($tag))->toBeTrue();
});

test('soft-deleted posts are excluded from normal and published queries', function (): void {
    $author = blogAuthor();
    $post = Post::query()->create(blogPostData($author, [
        'slug' => 'deleted-post', 'status' => PostStatus::Published, 'published_at' => now(),
    ]));
    $post->delete();

    expect(Post::query()->whereKey($post)->exists())->toBeFalse()
        ->and(Post::published()->whereKey($post)->exists())->toBeFalse()
        ->and(Post::withTrashed()->whereKey($post)->exists())->toBeTrue();
});

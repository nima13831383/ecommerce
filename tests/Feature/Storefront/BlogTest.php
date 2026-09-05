<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\User;

function storefrontPublishedPost(string $slug, array $overrides = []): Post
{
    return Post::query()->create(array_replace([
        'author_id' => User::factory()->create()->id,
        'title' => 'مقاله '.$slug,
        'slug' => $slug,
        'excerpt' => 'خلاصه مقاله '.$slug,
        'content' => '<p>محتوای مقاله '.$slug.'</p>',
        'status' => PostStatus::Published,
        'published_at' => now()->subDay(),
    ], $overrides));
}

test('blog listing exposes only published posts with taxonomy, search, pagination, and stable ordering', function (): void {
    $category = PostCategory::query()->create(['name' => 'عطر و ادکلن']);
    $tag = PostTag::query()->create(['name' => 'راهنما']);
    $old = storefrontPublishedPost('مقاله-قدیمی', ['published_at' => now()->subDays(2)]);
    $new = storefrontPublishedPost('مقاله-فارسی-جدید', ['title' => 'راهنمای انتخاب عطر']);
    $new->categories()->attach($category);
    $new->postTags()->attach($tag);
    Post::query()->create(['title' => 'پیش‌نویس', 'slug' => 'draft', 'content' => 'draft', 'status' => PostStatus::Draft]);
    Post::query()->create(['title' => 'آینده', 'slug' => 'future', 'content' => 'future', 'status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);

    $response = $this->get(route('storefront.blog.index', ['category' => $category->slug, 'search' => 'انتخاب']));

    $response->assertOk()
        ->assertSee('راهنمای انتخاب عطر')
        ->assertSee('عطر و ادکلن')
        ->assertSee('مقاله-فارسی-جدید')
        ->assertDontSee('پیش‌نویس')
        ->assertDontSee('آینده')
        ->assertDontSee('idempotency_key')
        ->assertDontSee('admin_note');

    expect($old->fresh()->slug)->toBe('مقاله-قدیمی');
});

test('blog listing paginates, returns an empty state, and article detail uses unicode slug and related posts', function (): void {
    for ($index = 0; $index < 10; $index++) {
        storefrontPublishedPost('post-'.$index, ['title' => "مطلب {$index}"]);
    }

    $current = storefrontPublishedPost('راهنمای-فارسی', ['title' => 'راهنمای فارسی', 'content' => '<h2>محتوای واقعی</h2><p>متن مقاله</p>']);
    storefrontPublishedPost('مقاله-مرتبط', ['title' => 'مطلب مرتبط']);

    $this->get(route('storefront.blog.index', ['page' => 2]))
        ->assertOk()
        ->assertSee('article-pagination');
    $this->get(route('storefront.blog.index', ['search' => 'غیرممکن']))
        ->assertOk()
        ->assertSee('مقاله‌ای پیدا نشد');

    $this->get(route('storefront.blog.show', ['post' => $current->slug]))
        ->assertOk()
        ->assertSee('راهنمای فارسی')
        ->assertSee('محتوای واقعی')
        ->assertSee('مطلب مرتبط')
        ->assertDontSee('gateway_response');

    $future = storefrontPublishedPost('scheduled-future', ['status' => PostStatus::Scheduled, 'published_at' => now()->addDay()]);
    $draft = Post::query()->create(['title' => 'draft detail', 'slug' => 'draft-detail', 'content' => 'draft', 'status' => PostStatus::Draft]);
    $this->get(route('storefront.blog.show', ['post' => $future->slug]))->assertNotFound();
    $this->get(route('storefront.blog.show', ['post' => $draft->slug]))->assertNotFound();
});

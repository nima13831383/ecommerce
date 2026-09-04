<?php

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('the storefront demo blog command creates realistic public and control content', function (): void {
    $this->artisan('demo:storefront-blog')->assertExitCode(Command::SUCCESS);

    $demo = Post::query()->where('content', 'like', '%storefront-demo-blog%');

    expect(PostCategory::query()->whereIn('slug', [
        'راهنمای-خرید', 'عطر-و-ادکلن', 'مراقبت-پوست', 'زیبایی-و-آرایش', 'استایل-و-اکسسوری',
    ])->count())->toBe(5)
        ->and(PostTag::query()->whereIn('slug', [
            'راهنمای-خرید', 'عطر', 'پوست', 'آرایش', 'اکسسوری', 'انتخاب-محصول', 'نگهداری', 'سبک-زندگی', 'هدیه', 'محبوب',
        ])->count())->toBe(10)
        ->and($demo->clone()->where('status', PostStatus::Published->value)->count())->toBe(18)
        ->and($demo->clone()->where('status', PostStatus::Draft->value)->where('slug', 'پیش-نویس-داخلی-وبلاگ')->count())->toBe(1)
        ->and($demo->clone()->where('status', PostStatus::Scheduled->value)->count())->toBe(2)
        ->and($demo->clone()->where('status', PostStatus::Published->value)->whereNotNull('featured_image')->count())->toBe(16)
        ->and($demo->clone()->where('status', PostStatus::Published->value)->whereNull('featured_image')->count())->toBe(2);

    expect($demo->clone()->where('status', PostStatus::Published->value)->pluck('slug')->unique())->toHaveCount(18);

    $categoryCounts = PostCategory::query()
        ->whereIn('slug', ['راهنمای-خرید', 'عطر-و-ادکلن', 'مراقبت-پوست', 'زیبایی-و-آرایش', 'استایل-و-اکسسوری'])
        ->withCount(['posts' => fn ($query) => $query->published()])
        ->pluck('posts_count', 'slug');

    expect($categoryCounts->min())->toBeGreaterThanOrEqual(3)
        ->and(Storage::disk('public')->allFiles('blog/demo'))->toHaveCount(18);
});

test('the storefront demo blog command is idempotent and supports public blog verification', function (): void {
    $this->artisan('demo:storefront-blog')->assertExitCode(Command::SUCCESS);

    $before = [
        'posts' => Post::query()->where('content', 'like', '%storefront-demo-blog%')->count(),
        'categories' => PostCategory::query()->count(),
        'tags' => PostTag::query()->count(),
        'files' => Storage::disk('public')->allFiles('blog/demo'),
    ];

    $this->artisan('demo:storefront-blog')->assertExitCode(Command::SUCCESS);

    expect([
        'posts' => Post::query()->where('content', 'like', '%storefront-demo-blog%')->count(),
        'categories' => PostCategory::query()->count(),
        'tags' => PostTag::query()->count(),
        'files' => Storage::disk('public')->allFiles('blog/demo'),
    ])->toBe($before);

    $this->get('/blog')->assertOk()->assertSee('مجله لوکسیر');
    $this->get('/blog?page=2')->assertOk();
    $this->get('/blog?category=عطر-و-ادکلن')->assertOk()->assertSee('عطر');
    $this->get('/blog?search=عطر')->assertOk()->assertSee('عطر');

    $slug = Post::query()->where('title', 'چطور عطر مناسب خودمان را انتخاب کنیم؟')->value('slug');
    expect($slug)->toBe('چطور-عطر-مناسب-خودمان-را-انتخاب-کنیم');

    $this->get(route('storefront.blog.show', ['post' => $slug]))
        ->assertOk()
        ->assertSee('چطور عطر مناسب خودمان را انتخاب کنیم؟')
        ->assertSee('مطالب مرتبط');

    $this->get('/blog/'.$before['posts'])->assertNotFound();

    expect(Post::query()->published()->where('slug', 'ترندهای-زیبایی-فصل-آینده')->exists())->toBeFalse()
        ->and(Post::query()->published()->where('slug', 'پیش-نویس-داخلی-وبلاگ')->exists())->toBeFalse();
});

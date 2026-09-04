<?php

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function finalPostQaUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function finalPostQaPermissions(): array
{
    return ['posts.viewAny', 'posts.view', 'posts.create', 'posts.update', 'posts.delete', 'posts.restore', 'posts.publish'];
}

function finalPostQaCreate(User $user, string $title): Post
{
    Livewire::actingAs($user, 'web')->test(CreatePost::class)
        ->fillForm([
            'author_id' => (string) $user->id,
            'title' => $title,
            'content' => '<p>Runtime lifecycle content</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    return Post::query()->where('title', $title)->firstOrFail();
}

test('future scheduling, invalid schedules, edits, unpublish and publish use real Filament actions', function (): void {
    $admin = finalPostQaUser(finalPostQaPermissions());
    $post = finalPostQaCreate($admin, 'Scheduled runtime post');
    $slug = $post->slug;
    $future = CarbonImmutable::now()->addDay()->startOfMinute();

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('schedule')
        ->setActionData(['published_at' => $future->format('Y-m-d H:i:s')])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $post = $post->fresh();
    expect($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->published_at?->equalTo($future))->toBeTrue()
        ->and($post->slug)->toBe($slug);

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('schedule')
        ->setActionData(['published_at' => CarbonImmutable::now()->subMinute()->format('Y-m-d H:i:s')])
        ->callMountedAction()
        ->assertHasActionErrors(['published_at']);
    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->fillForm(['title' => 'Edited scheduled runtime post', 'content' => '<p>Edited</p>'])
        ->call('save')
        ->assertHasNoFormErrors();
    $post = $post->fresh();
    expect($post->title)->toBe('Edited scheduled runtime post')
        ->and($post->status)->toBe(PostStatus::Scheduled)
        ->and($post->published_at?->equalTo($future))->toBeTrue()
        ->and($post->slug)->toBe($slug);

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('unpublish')->callMountedAction();
    expect($post->fresh()->status)->toBe(PostStatus::Draft)->and($post->fresh()->published_at)->toBeNull();

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('publish')->callMountedAction();
    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

test('scheduled post can be deleted and restored through Filament with taxonomy intact', function (): void {
    $admin = finalPostQaUser(finalPostQaPermissions());
    $category = PostCategory::query()->create(['name' => 'Lifecycle category', 'slug' => 'lifecycle-category']);
    $tag = PostTag::query()->create(['name' => 'Lifecycle tag', 'slug' => 'lifecycle-tag']);
    $post = finalPostQaCreate($admin, 'Delete and restore runtime post');
    $post->categories()->attach($category);
    $post->postTags()->attach($tag);
    $post->refresh();

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('delete')->callMountedAction();
    $deleted = Post::withTrashed()->findOrFail($post->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->categories()->whereKey($category->id)->exists())->toBeTrue()
        ->and($deleted->postTags()->whereKey($tag->id)->exists())->toBeTrue()
        ->and(PostCategory::query()->whereKey($category->id)->exists())->toBeTrue()
        ->and(PostTag::query()->whereKey($tag->id)->exists())->toBeTrue();

    Livewire::actingAs($admin, 'web')->test(EditPost::class, ['record' => $deleted->getRouteKey()])
        ->mountAction('restore')->callMountedAction();
    $restored = $post->fresh();
    expect($restored->trashed())->toBeFalse()
        ->and($restored->title)->toBe('Delete and restore runtime post')
        ->and($restored->slug)->toBe('delete-and-restore-runtime-post')
        ->and($restored->categories()->whereKey($category->id)->exists())->toBeTrue()
        ->and($restored->postTags()->whereKey($tag->id)->exists())->toBeTrue();
});

test('delete and restore actions reject unauthorized direct Livewire calls', function (): void {
    $admin = finalPostQaUser(finalPostQaPermissions());
    $viewer = finalPostQaUser(['posts.viewAny', 'posts.view', 'posts.update']);
    $post = finalPostQaCreate($admin, 'Authorization lifecycle post');

    Livewire::actingAs($viewer, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertActionHidden('delete')
        ->mountAction('delete')->callMountedAction();
    expect($post->fresh()->trashed())->toBeFalse();

    $post->delete();
    Livewire::actingAs($viewer, 'web')->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->assertActionHidden('restore')
        ->mountAction('restore')->callMountedAction();
    expect($post->fresh()->trashed())->toBeTrue();
});

<?php

use App\Enums\PostStatus;
use App\Filament\Resources\Posts\Pages\CreatePost;
use App\Filament\Resources\Posts\Pages\EditPost;
use App\Filament\Resources\Posts\PostResource;
use App\Models\Post;
use App\Models\User;
use App\Policies\PostPolicy;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function blogAdmin(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(fn (string $permission) => Permission::findOrCreate($permission, 'web')));

    return $user;
}

test('blog administration is permission protected and exposes expected resource routes', function (): void {
    $this->actingAs(User::factory()->create())->get('/admin/posts')->assertForbidden();
    $editor = blogAdmin(['posts.viewAny', 'posts.view', 'posts.create', 'posts.update']);

    $this->actingAs($editor)->get('/admin/posts')->assertOk();
    expect(PostResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});

test('editor can create a draft through Filament but cannot publish without publish permission', function (): void {
    $editor = blogAdmin(['posts.viewAny', 'posts.view', 'posts.create', 'posts.update']);

    Livewire::actingAs($editor, 'web')
        ->test(CreatePost::class)
        ->fillForm([
            'author_id' => (string) $editor->id,
            'title' => 'نوشته فیلامنت',
            'content' => '<p>محتوا</p>',
        ], 'form')
        ->call('create')
        ->assertHasNoFormErrors();

    $post = Post::query()->where('title', 'نوشته فیلامنت')->firstOrFail();
    expect($post->status)->toBe(PostStatus::Draft);

    $this->actingAs($editor)->get('/admin/posts/'.$post->id.'/edit')->assertOk();
    expect(app(PostPolicy::class)->publish($editor, $post))->toBeFalse();
});

test('publisher uses an explicit Filament action to publish', function (): void {
    $publisher = blogAdmin(['posts.viewAny', 'posts.view', 'posts.update', 'posts.publish']);
    $post = Post::query()->create([
        'author_id' => $publisher->id,
        'title' => 'پیش‌نویس انتشار',
        'slug' => 'publish-action',
        'content' => '<p>محتوا</p>',
        'status' => PostStatus::Draft,
    ]);

    Livewire::actingAs($publisher, 'web')
        ->test(EditPost::class, ['record' => $post->getRouteKey()])
        ->mountAction('publish')
        ->callMountedAction();

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and($post->fresh()->published_at)->not->toBeNull();
});

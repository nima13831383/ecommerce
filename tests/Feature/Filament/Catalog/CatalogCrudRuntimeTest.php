<?php

use App\Filament\Resources\Brands\Pages\CreateBrand;
use App\Filament\Resources\Brands\Pages\EditBrand;
use App\Filament\Resources\Brands\Pages\ListBrands;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Filament\Resources\PostCategories\Pages\CreatePostCategory;
use App\Filament\Resources\PostCategories\Pages\EditPostCategory;
use App\Filament\Resources\PostCategories\Pages\ListPostCategories;
use App\Filament\Resources\PostTags\Pages\CreatePostTag;
use App\Filament\Resources\PostTags\Pages\EditPostTag;
use App\Filament\Resources\PostTags\Pages\ListPostTags;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostTag;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function catalogQaUser(array $permissions): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(collect($permissions)->map(
        fn (string $permission): Permission => Permission::findOrCreate($permission, 'web'),
    ));

    return $user;
}

function catalogQaPermissions(string $prefix): array
{
    return ["{$prefix}.view", "{$prefix}.create", "{$prefix}.update", "{$prefix}.delete", "{$prefix}.restore", "{$prefix}.force-delete"];
}

test('Product categories, brands, and tags complete their real Filament CRUD contracts', function (): void {
    $admin = catalogQaUser([
        ...catalogQaPermissions('categories'),
        ...catalogQaPermissions('brands'),
        ...catalogQaPermissions('tags'),
    ]);

    Livewire::actingAs($admin, 'web')->test(CreateCategory::class)
        ->fillForm(['name' => 'دسته والد', 'slug' => 'catalog-parent', 'description' => 'Parent', 'sort_order' => 1, 'is_active' => true, 'is_featured' => false, 'is_hidden' => false])
        ->call('create')->assertHasNoFormErrors();
    $parent = Category::query()->where('slug', 'catalog-parent')->firstOrFail();

    Livewire::actingAs($admin, 'web')->test(CreateCategory::class)
        ->fillForm(['parent_id' => $parent->id, 'name' => 'دسته فرزند', 'slug' => 'catalog-child', 'sort_order' => 2, 'is_active' => true, 'is_featured' => false, 'is_hidden' => false])
        ->call('create')->assertHasNoFormErrors();
    $category = Category::query()->where('slug', 'catalog-child')->firstOrFail();

    Livewire::actingAs($admin, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['name' => 'دسته فرزند ویرایش', 'slug' => 'catalog-child-edited'])
        ->call('save')->assertHasNoFormErrors();
    expect($category->fresh()->name)->toBe('دسته فرزند ویرایش');

    Livewire::actingAs($admin, 'web')->test(CreateBrand::class)
        ->fillForm(['name' => 'برند تست', 'slug' => 'catalog-brand', 'description' => 'Brand', 'sort_order' => 1, 'is_active' => true, 'is_featured' => false])
        ->call('create')->assertHasNoFormErrors();
    $brand = Brand::query()->where('slug', 'catalog-brand')->firstOrFail();
    Livewire::actingAs($admin, 'web')->test(EditBrand::class, ['record' => $brand->getRouteKey()])
        ->fillForm(['name' => 'برند ویرایش', 'slug' => 'catalog-brand-edited', 'is_active' => false])
        ->call('save')->assertHasNoFormErrors();
    expect($brand->fresh()->name)->toBe('برند ویرایش')->and($brand->fresh()->is_active)->toBeFalse();

    Livewire::actingAs($admin, 'web')->test(CreateTag::class)
        ->fillForm(['name' => 'برچسب تست', 'slug' => 'catalog-tag'])
        ->call('create')->assertHasNoFormErrors();
    $tag = Tag::query()->where('slug', 'catalog-tag')->firstOrFail();
    Livewire::actingAs($admin, 'web')->test(EditTag::class, ['record' => $tag->getRouteKey()])
        ->fillForm(['name' => 'برچسب ویرایش', 'slug' => 'catalog-tag-edited'])
        ->call('save')->assertHasNoFormErrors();
    expect($tag->fresh()->name)->toBe('برچسب ویرایش');

    Livewire::actingAs($admin, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])->mountAction('delete')->callMountedAction();
    expect($category->fresh()->trashed())->toBeTrue();
    Livewire::actingAs($admin, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])->mountAction('restore')->callMountedAction();
    expect($category->fresh()->trashed())->toBeFalse();

    Livewire::actingAs($admin, 'web')->test(EditBrand::class, ['record' => $brand->getRouteKey()])->mountAction('delete')->callMountedAction();
    expect($brand->fresh()->trashed())->toBeTrue();
    Livewire::actingAs($admin, 'web')->test(EditBrand::class, ['record' => $brand->getRouteKey()])->mountAction('restore')->callMountedAction();
    expect($brand->fresh()->trashed())->toBeFalse();

    Livewire::actingAs($admin, 'web')->test(ListTags::class)->callTableAction('delete', $tag);
    expect(Tag::query()->find($tag->id))->toBeNull();
});

test('catalog references and hierarchy remain valid through actual delete actions', function (): void {
    $admin = catalogQaUser([...catalogQaPermissions('categories'), ...catalogQaPermissions('brands'), ...catalogQaPermissions('tags')]);
    $category = Category::query()->create(['name' => 'Used category', 'slug' => 'used-category']);
    $brand = Brand::query()->create(['name' => 'Used brand', 'slug' => 'used-brand']);
    $tag = Tag::query()->create(['name' => 'Used tag', 'slug' => 'used-tag']);
    $product = Product::query()->create(['name' => 'Catalog relation product', 'slug' => 'catalog-relation-product', 'type' => 'simple', 'price' => 10000, 'brand_id' => $brand->id]);
    $product->categories()->attach($category);
    $product->tags()->attach($tag);

    Livewire::actingAs($admin, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])->mountAction('delete')->callMountedAction();
    Livewire::actingAs($admin, 'web')->test(EditBrand::class, ['record' => $brand->getRouteKey()])->mountAction('delete')->callMountedAction();
    Livewire::actingAs($admin, 'web')->test(ListTags::class)->callTableAction('delete', $tag);

    expect($product->fresh()->brand_id)->toBe($brand->id)
        ->and($brand->fresh()->trashed())->toBeTrue()
        ->and($product->categories()->withTrashed()->whereKey($category->id)->exists())->toBeTrue()
        ->and($product->tags()->whereKey($tag->id)->exists())->toBeFalse();

    $child = Category::query()->create(['parent_id' => $category->id, 'name' => 'Child', 'slug' => 'used-child']);
    expect($child->parent_id)->toBe($category->id)
        ->and(Category::withTrashed()->find($child->parent_id)?->is($category->fresh()))->toBeTrue();
    Livewire::actingAs($admin, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])
        ->fillForm(['parent_id' => $category->id])->call('save')->assertHasFormErrors(['parent_id']);
    expect($category->fresh()->parent_id)->toBeNull();
});

test('catalog forms reject duplicate category brand and tag slugs', function (): void {
    $admin = catalogQaUser([...catalogQaPermissions('categories'), ...catalogQaPermissions('brands'), ...catalogQaPermissions('tags')]);
    $category = Category::query()->create(['name' => 'Existing category', 'slug' => 'duplicate-category']);
    $brand = Brand::query()->create(['name' => 'Existing brand', 'slug' => 'duplicate-brand']);
    $tag = Tag::query()->create(['name' => 'Existing tag', 'slug' => 'duplicate-tag']);

    Livewire::actingAs($admin, 'web')->test(CreateCategory::class)->fillForm(['name' => 'Another category', 'slug' => $category->slug])->call('create')->assertHasFormErrors(['slug']);
    Livewire::actingAs($admin, 'web')->test(CreateBrand::class)->fillForm(['name' => 'Another brand', 'slug' => $brand->slug])->call('create')->assertHasFormErrors(['slug']);
    Livewire::actingAs($admin, 'web')->test(CreateTag::class)->fillForm(['name' => 'Another tag', 'slug' => $tag->slug])->call('create')->assertHasFormErrors(['slug']);
});

test('post taxonomy forms reject duplicate slugs through validation before persistence', function (): void {
    $admin = catalogQaUser([...catalogQaPermissions('post-categories'), ...catalogQaPermissions('post-tags')]);
    $category = PostCategory::query()->create(['name' => 'Existing post category', 'slug' => 'duplicate-post-category']);
    $tag = PostTag::query()->create(['name' => 'Existing post tag', 'slug' => 'duplicate-post-tag']);

    Livewire::actingAs($admin, 'web')->test(CreatePostCategory::class)
        ->fillForm(['name' => 'Another post category', 'slug' => $category->slug])
        ->call('create');
    Livewire::actingAs($admin, 'web')->test(CreatePostTag::class)
        ->fillForm(['name' => 'Another post tag', 'slug' => $tag->slug])
        ->call('create');

    expect(PostCategory::query()->where('name', 'Another post category')->exists())->toBeFalse()
        ->and(PostTag::query()->where('name', 'Another post tag')->exists())->toBeFalse();
});

test('post taxonomy resources create edit and delete through Filament and enforce authorization', function (): void {
    $admin = catalogQaUser([...catalogQaPermissions('post-categories'), ...catalogQaPermissions('post-tags'), 'posts.viewAny', 'posts.view', 'posts.create', 'posts.update']);
    Livewire::actingAs($admin, 'web')->test(CreatePostCategory::class)->fillForm(['name' => 'Blog parent', 'slug' => 'blog-parent', 'description' => 'Parent'])->call('create')->assertHasNoFormErrors();
    $parent = PostCategory::query()->where('slug', 'blog-parent')->firstOrFail();
    Livewire::actingAs($admin, 'web')->test(CreatePostCategory::class)->fillForm(['parent_id' => $parent->id, 'name' => 'Blog child', 'slug' => 'blog-child'])->call('create')->assertHasNoFormErrors();
    $category = PostCategory::query()->where('slug', 'blog-child')->firstOrFail();
    Livewire::actingAs($admin, 'web')->test(CreatePostTag::class)->fillForm(['name' => 'Blog tag', 'slug' => 'blog-tag'])->call('create')->assertHasNoFormErrors();
    $tag = PostTag::query()->where('slug', 'blog-tag')->firstOrFail();

    Livewire::actingAs($admin, 'web')->test(EditPostCategory::class, ['record' => $category->getRouteKey()])->fillForm(['name' => 'Blog child edited', 'slug' => 'blog-child-edited'])->call('save')->assertHasNoFormErrors();
    Livewire::actingAs($admin, 'web')->test(EditPostTag::class, ['record' => $tag->getRouteKey()])->fillForm(['name' => 'Blog tag edited', 'slug' => 'blog-tag-edited'])->call('save')->assertHasNoFormErrors();

    $post = Post::query()->create(['author_id' => $admin->id, 'title' => 'Taxonomy post', 'slug' => 'taxonomy-post', 'content' => '<p>Content</p>']);
    $post->categories()->attach($category);
    $post->postTags()->attach($tag);
    Livewire::actingAs($admin, 'web')->test(ListPostCategories::class)->callTableAction('delete', $category);
    Livewire::actingAs($admin, 'web')->test(ListPostTags::class)->callTableAction('delete', $tag);

    expect(PostCategory::query()->find($category->id))->toBeNull()
        ->and(PostTag::query()->find($tag->id))->toBeNull()
        ->and($post->categories()->whereKey($category->id)->exists())->toBeFalse()
        ->and($post->postTags()->whereKey($tag->id)->exists())->toBeFalse();

    $viewer = catalogQaUser(['post-categories.view', 'post-tags.view']);
    Livewire::actingAs($viewer, 'web')->test(CreatePostCategory::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(CreatePostTag::class)->assertForbidden();
    expect(PostCategory::query()->where('slug', 'unauthorized-category')->exists())->toBeFalse()
        ->and(PostTag::query()->where('slug', 'unauthorized-tag')->exists())->toBeFalse();
});

test('catalog create edit and delete surfaces enforce authorization for every support resource', function (): void {
    $admin = catalogQaUser([
        ...catalogQaPermissions('categories'),
        ...catalogQaPermissions('brands'),
        ...catalogQaPermissions('tags'),
        ...catalogQaPermissions('post-categories'),
        ...catalogQaPermissions('post-tags'),
    ]);
    $category = Category::query()->create(['name' => 'Auth category', 'slug' => 'auth-category']);
    $brand = Brand::query()->create(['name' => 'Auth brand', 'slug' => 'auth-brand']);
    $tag = Tag::query()->create(['name' => 'Auth tag', 'slug' => 'auth-tag']);
    $postCategory = PostCategory::query()->create(['name' => 'Auth post category', 'slug' => 'auth-post-category']);
    $postTag = PostTag::query()->create(['name' => 'Auth post tag', 'slug' => 'auth-post-tag']);
    $viewer = catalogQaUser([
        'categories.view', 'brands.view', 'tags.view', 'post-categories.view', 'post-tags.view',
    ]);

    Livewire::actingAs($viewer, 'web')->test(CreateCategory::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(EditCategory::class, ['record' => $category->getRouteKey()])->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(CreateBrand::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(EditBrand::class, ['record' => $brand->getRouteKey()])->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(CreateTag::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(EditTag::class, ['record' => $tag->getRouteKey()])->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(CreatePostCategory::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(EditPostCategory::class, ['record' => $postCategory->getRouteKey()])->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(CreatePostTag::class)->assertForbidden();
    Livewire::actingAs($viewer, 'web')->test(EditPostTag::class, ['record' => $postTag->getRouteKey()])->assertForbidden();

    $deleteViewer = catalogQaUser([
        'categories.view', 'categories.update', 'brands.view', 'brands.update', 'tags.view', 'tags.update',
        'post-categories.view', 'post-categories.update', 'post-tags.view', 'post-tags.update',
    ]);
    Livewire::actingAs($deleteViewer, 'web')->test(ListCategories::class)->assertTableActionHidden('delete', $category);
    Livewire::actingAs($deleteViewer, 'web')->test(ListBrands::class)->assertTableActionHidden('delete', $brand);
    Livewire::actingAs($deleteViewer, 'web')->test(ListTags::class)->assertTableActionHidden('delete', $tag);
    Livewire::actingAs($deleteViewer, 'web')->test(ListPostCategories::class)->assertTableActionHidden('delete', $postCategory);
    Livewire::actingAs($deleteViewer, 'web')->test(ListPostTags::class)->assertTableActionHidden('delete', $postTag);
});

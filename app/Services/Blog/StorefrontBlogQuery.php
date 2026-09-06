<?php

namespace App\Services\Blog;

use App\Enums\CacheRebuildDomain;
use App\Models\Post;
use App\Models\PostCategory;
use App\Services\Storefront\StorefrontQueryCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StorefrontBlogQuery
{
    public function __construct(private readonly StorefrontQueryCache $cache) {}

    public function paginate(?string $category = null, ?string $search = null, int $perPage = 9): LengthAwarePaginator
    {
        $category = filled($category) ? trim($category) : null;
        $search = filled($search) ? trim($search) : null;
        $page = request()->integer('page', 1);

        $paginator = $this->cache->remember(
            CacheRebuildDomain::Blog,
            'archive',
            ['category' => $category, 'search' => $search, 'per_page' => $perPage, 'page' => $page],
            fn (): LengthAwarePaginator => $this->paginateUncached($category, $search, $perPage, $page),
        );

        return $paginator
            ->setPath(route('storefront.blog.index'))
            ->appends(request()->except('page'));
    }

    public function findPublished(string $slug): Post
    {
        return $this->cache->remember(
            CacheRebuildDomain::Blog,
            'detail',
            ['slug' => $slug],
            fn (): Post => $this->findPublishedUncached($slug),
        );
    }

    public function paginateUncached(?string $category = null, ?string $search = null, int $perPage = 9, int $page = 1): LengthAwarePaginator
    {
        $query = $this->published()->with(['categories', 'tags'])->latest('published_at');

        if ($category !== null && $category !== '') {
            $query->whereHas(
                'categories',
                fn (Builder $categories): Builder => $categories->where('slug', $category),
            );
        }

        if ($search !== null && trim($search) !== '') {
            $term = trim($search);
            $query->where(function (Builder $searchQuery) use ($term): void {
                $searchQuery->where('title', 'like', "%{$term}%")
                    ->orWhere('excerpt', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function findPublishedUncached(string $slug): Post
    {
        return $this->published()->with(['categories', 'tags', 'author'])->where('slug', $slug)->firstOrFail();
    }

    public function categories(): Collection
    {
        return PostCategory::query()
            ->whereHas('posts', fn (Builder $posts): Builder => $posts->published())
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    public function related(Post $post, int $limit = 3): Collection
    {
        $categoryIds = $post->categories->modelKeys();

        return $this->published()
            ->with(['categories', 'tags'])
            ->when(
                $categoryIds !== [],
                fn (Builder $query): Builder => $query->whereHas(
                    'categories',
                    fn (Builder $categories): Builder => $categories->whereIn('post_categories.id', $categoryIds),
                ),
            )
            ->where('posts.id', '!=', $post->getKey())
            ->latest('published_at')
            ->limit($limit)
            ->get();
    }

    private function published(): Builder
    {
        return Post::query()->published();
    }
}

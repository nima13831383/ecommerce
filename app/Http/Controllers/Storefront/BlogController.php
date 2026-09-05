<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Services\Blog\StorefrontBlogQuery;
use App\Services\Settings\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function __construct(
        private readonly StorefrontBlogQuery $blog,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): View
    {
        $category = $request->string('category')->toString();
        $search = $request->string('search')->toString();

        return view('storefront.blog.index', [
            'posts' => $this->blog->paginate($category, $search, $this->settings->get('blog.posts_per_page')),
            'categories' => $this->blog->categories(),
            'category' => $category,
            'search' => $search,
            'title' => 'مجله لوکسیر | لوکسیر',
        ]);
    }

    public function show(string $post): View
    {
        $article = $this->blog->findPublished($post);

        return view('storefront.blog.show', [
            'post' => $article,
            'related' => $this->blog->related($article),
            'title' => $article->title.' | مجله لوکسیر',
        ]);
    }
}

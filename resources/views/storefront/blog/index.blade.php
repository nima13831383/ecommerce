@extends('storefront.layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/blog/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/blog/responsive.css') }}">
@endpush

@section('content')
    <div class="public-page"><div class="site-container">
        <div class="public-breadcrumb"><a href="{{ route('storefront.home') }}">خانه</a><span>/</span><span>وبلاگ</span></div>
        <section class="blog-intro"><h1>مجله لوکسیر</h1><p>مطالب، راهنماها و نکات مرتبط با زیبایی، عطر و انتخاب بهتر محصولات.</p></section>
        <form class="blog-filters" method="GET" action="{{ route('storefront.blog.index') }}" aria-label="فیلتر مقالات">
            <a class="blog-filter {{ $category === '' ? 'is-active' : '' }}" href="{{ route('storefront.blog.index', $search ? ['search' => $search] : []) }}">همه</a>
            @foreach ($categories as $item)
                <a class="blog-filter {{ $category === $item->slug ? 'is-active' : '' }}" href="{{ route('storefront.blog.index', array_filter(['category' => $item->slug, 'search' => $search])) }}">{{ $item->name }}</a>
            @endforeach
            @if ($search !== '')<input type="search" name="search" value="{{ $search }}" placeholder="جستجو در مقالات" aria-label="جستجو در مقالات">@endif
        </form>
        @if ($posts->isNotEmpty() && $posts->currentPage() === 1)
            @php($featured = $posts->first())
            <article class="public-card featured-article">
                <div class="article-media">@if ($featured->featured_image)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.public_disk', 'public'))->url($featured->featured_image) }}" alt="{{ $featured->title }}">@else<span>جای تصویر مقاله منتخب</span>@endif</div>
                <div class="article-copy">@if ($featured->categories->first())<span class="article-badge">{{ $featured->categories->first()->name }}</span>@endif<h2>{{ $featured->title }}</h2>@if ($featured->excerpt)<p>{{ $featured->excerpt }}</p>@endif<a class="article-link" href="{{ route('storefront.blog.show', ['post' => $featured->slug]) }}">ادامه مطلب</a></div>
            </article>
        @endif
        <div class="article-grid">
            @forelse ($posts as $post)
                @if ($posts->currentPage() === 1 && $loop->first) @continue @endif
                @include('storefront.components.blog-card', ['post' => $post])
            @empty
                <div class="empty-state"><h2>مقاله‌ای پیدا نشد.</h2><p>در حال حاضر مطلبی با این فیلتر منتشر نشده است.</p></div>
            @endforelse
        </div>
        {{ $posts->links('storefront.components.pagination') }}
    </div></div>
@endsection

@push('scripts')
    <script src="{{ asset('storefront/assets/js/blog/blog-filter.js') }}" defer></script>
@endpush

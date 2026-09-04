@extends('storefront.layouts.app')

@push('head')
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/blog/layout.css') }}">
    <link rel="stylesheet" href="{{ asset('storefront/assets/css/blog/responsive.css') }}">
@endpush

@section('content')
    <div class="public-page"><div class="site-container">
        <div class="public-breadcrumb"><a href="{{ route('storefront.home') }}">خانه</a><span>/</span><a href="{{ route('storefront.blog.index') }}">وبلاگ</a><span>/</span><span>{{ $post->title }}</span></div>
        <article class="article-page">
            <header class="article-header">
                @if ($post->categories->first())<span class="article-badge">{{ $post->categories->first()->name }}</span>@endif
                <h1>{{ $post->title }}</h1>
                <div class="article-meta"><span>{{ \App\Support\JalaliDate::format($post->published_at, 'j F Y') }}</span>@if ($post->author)<span>نویسنده: {{ $post->author->name }}</span>@endif</div>
            </header>
            <div class="article-media">@if ($post->featured_image)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.public_disk', 'public'))->url($post->featured_image) }}" alt="{{ $post->title }}">@else<span>جای تصویر اصلی مقاله</span>@endif</div>
            <div class="article-layout"><div class="article-body">{!! $post->content !!}</div><aside class="public-card toc-card"><h2>دسته‌بندی</h2>@foreach ($post->categories as $category)<a href="{{ route('storefront.blog.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>@endforeach</aside></div>
            @if ($post->tags->isNotEmpty())<div class="article-meta">@foreach ($post->tags as $tag)<span>#{{ $tag->name }}</span>@endforeach</div>@endif
            @if ($related->isNotEmpty())<section class="related-section"><h2>مطالب مرتبط</h2><div class="article-grid">@foreach ($related as $item)@include('storefront.components.blog-card', ['post' => $item])@endforeach</div></section>@endif
        </article>
    </div></div>
@endsection

@push('scripts')
    <script src="{{ asset('storefront/assets/js/blog/article.js') }}" defer></script>
@endpush

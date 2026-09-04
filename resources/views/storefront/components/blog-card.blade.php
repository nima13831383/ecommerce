<article class="article-card">
    <div class="article-media">@if ($post->featured_image)<img src="{{ \Illuminate\Support\Facades\Storage::disk(config('media.public_disk', 'public'))->url($post->featured_image) }}" alt="{{ $post->title }}" loading="lazy">@else<span>جای تصویر مقاله</span>@endif</div>
    <div class="article-card__body">
        @if ($post->categories->first())<span class="article-badge">{{ $post->categories->first()->name }}</span>@endif
        <h2><a href="{{ route('storefront.blog.show', ['post' => $post->slug]) }}">{{ $post->title }}</a></h2>
        @if ($post->excerpt)<p class="article-excerpt">{{ $post->excerpt }}</p>@endif
        <div class="article-meta"><span>{{ \App\Support\JalaliDate::format($post->published_at, 'j F Y') }}</span></div>
        <a class="article-link" href="{{ route('storefront.blog.show', ['post' => $post->slug]) }}">ادامه مطلب</a>
    </div>
</article>

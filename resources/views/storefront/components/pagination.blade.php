@props(['paginator', 'variant' => 'category'])

@if ($paginator->hasPages())
    <nav class="{{ $variant === 'article' ? 'article-pagination' : 'category-pagination' }}" aria-label="صفحه‌بندی">
        @if ($variant === 'category')
            @if ($paginator->onFirstPage())
                <span class="is-disabled" aria-disabled="true">قبلی</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
            @endif
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page === $paginator->currentPage())
                <a href="{{ $url }}" aria-current="page" @class(['is-active' => $variant === 'article'])>{{ \App\Support\PersianNumber::digits($page) }}</a>
            @else
                <a href="{{ $url }}">{{ \App\Support\PersianNumber::digits($page) }}</a>
            @endif
        @endforeach

        @if ($variant === 'category')
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
            @else
                <span class="is-disabled" aria-disabled="true">بعدی</span>
            @endif
        @endif
    </nav>
@endif

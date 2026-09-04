@if ($paginator->hasPages())
    <nav class="category-pagination" aria-label="صفحه‌بندی محصولات">
        @if ($paginator->onFirstPage())
            <span class="is-disabled" aria-disabled="true">قبلی</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">قبلی</a>
        @endif

        @foreach ($paginator->getUrlRange(max(1, $paginator->currentPage() - 2), min($paginator->lastPage(), $paginator->currentPage() + 2)) as $page => $url)
            @if ($page === $paginator->currentPage())
                <a href="{{ $url }}" aria-current="page">{{ $page }}</a>
            @else
                <a href="{{ $url }}">{{ $page }}</a>
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">بعدی</a>
        @else
            <span class="is-disabled" aria-disabled="true">بعدی</span>
        @endif
    </nav>
@endif

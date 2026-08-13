@if ($paginator->hasPages())
    <div class="wd-full d-flex justify-content-center">
        <div class="tf-page-pagination">
            @if ($paginator->onFirstPage())
                <p class="pag-item disabled"><i class="icon icon-CaretLeft"></i></p>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="pag-item" rel="prev"><i class="icon icon-CaretLeft"></i></a>
            @endif

            @foreach ($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
                @if ($page === $paginator->currentPage())
                    <p class="pag-item active">{{ $page }}</p>
                @else
                    <a href="{{ $url }}" class="pag-item">{{ $page }}</a>
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="pag-item" rel="next"><i class="icon icon-CaretRightThin"></i></a>
            @else
                <p class="pag-item disabled"><i class="icon icon-CaretRightThin"></i></p>
            @endif
        </div>
    </div>
@endif

{{-- Phan trang duy nhat cua du an: Truoc | so trang | Sau. Mot bo nut, giu query string. --}}
@if ($paginator->hasPages())
    @php $paginator->appends(request()->query()); @endphp
    <nav aria-label="Phân trang">
        <ul class="pagination justify-content-center mb-0">
            {{-- Truoc --}}
            @if ($paginator->onFirstPage())
                <li class="page-item disabled"><span class="page-link">&lsaquo; Trước</span></li>
            @else
                <li class="page-item"><a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&lsaquo; Trước</a></li>
            @endif

            {{-- So trang (onEachSide(1): hien trang hien tai +/- 1) --}}
            @foreach ($paginator->onEachSide(1)->getUrlRange(max(1, $paginator->currentPage() - 1), min($paginator->lastPage(), $paginator->currentPage() + 1)) as $page => $url)
                @if ($page == $paginator->currentPage())
                    <li class="page-item active" aria-current="page"><span class="page-link">{{ $page }}</span></li>
                @else
                    <li class="page-item"><a class="page-link" href="{{ $url }}">{{ $page }}</a></li>
                @endif
            @endforeach

            {{-- Sau --}}
            @if ($paginator->hasMorePages())
                <li class="page-item"><a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Sau &rsaquo;</a></li>
            @else
                <li class="page-item disabled"><span class="page-link">Sau &rsaquo;</span></li>
            @endif
        </ul>
    </nav>
@endif

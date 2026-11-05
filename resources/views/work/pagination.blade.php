@if($paginator->hasPages())
<nav class="rb-pagination" aria-label="Navigasi halaman">
    <p class="rb-pagination-summary">Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} hasil</p>
    <div class="rb-pagination-controls">
    @if($paginator->onFirstPage())<span class="rb-page rb-page-disabled" aria-disabled="true">‹ Sebelumnya</span>@else<a class="rb-page" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Sebelumnya</a>@endif
    <div class="rb-page-numbers">
    @foreach($elements as $element)
        @if(is_string($element))<span class="rb-page rb-page-gap">{{ $element }}</span>@endif
        @if(is_array($element))@foreach($element as $page => $url)
            @if($page === $paginator->currentPage())<span class="rb-page rb-page-current" aria-current="page" aria-label="Halaman {{ $page }}">{{ $page }}</span>@else<a class="rb-page" href="{{ $url }}" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>@endif
        @endforeach @endif
    @endforeach
    </div>
    <span class="rb-page-position">{{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
    @if($paginator->hasMorePages())<a class="rb-page" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya ›</a>@else<span class="rb-page rb-page-disabled" aria-disabled="true">Berikutnya ›</span>@endif
    </div>
</nav>
@endif

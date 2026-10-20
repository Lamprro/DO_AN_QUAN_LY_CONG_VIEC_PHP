@if($paginator->hasPages())
<nav class="pagination-native" role="navigation" aria-label="Phân trang"><p>Hiển thị {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }} kết quả</p>
@if($paginator->onFirstPage())<span aria-disabled="true">‹ Trước</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Trước</a>@endif
@foreach($elements as $element)@if(is_string($element))<span>{{ $element }}</span>@else @foreach($element as $page=>$url)@if($page===$paginator->currentPage())<strong aria-current="page" aria-label="Trang {{ $page }}">{{ $page }}</strong>@else<a href="{{ $url }}" aria-label="Trang {{ $page }}">{{ $page }}</a>@endif @endforeach @endif @endforeach
@if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next">Sau ›</a>@else<span aria-disabled="true">Sau ›</span>@endif</nav>@endif

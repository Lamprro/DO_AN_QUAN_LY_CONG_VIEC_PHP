@if($paginator->total() > 0)
<nav class="pagination-native" role="navigation" aria-label="Phân trang" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin: 1.25rem 0; padding: 0.75rem 1rem; background: var(--surface, #fff); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-md, 8px);">
    <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
        <span class="muted" style="font-size: 0.85rem; color: var(--text-muted, #64748b);">
            Hiển thị <strong>{{ $paginator->firstItem() ?? 0 }}</strong>–<strong>{{ $paginator->lastItem() ?? 0 }}</strong> / <strong>{{ $paginator->total() }}</strong> kết quả
        </span>
        <div style="display: inline-flex; align-items: center; gap: 0.375rem;">
            <label for="per_page_select_{{ $paginator->getPageName() }}" style="margin: 0; font-size: 0.8125rem; color: var(--text-muted, #64748b); white-space: nowrap;">Bản ghi / trang:</label>
            <select id="per_page_select_{{ $paginator->getPageName() }}"
                    style="padding: 0.25rem 0.5rem; font-size: 0.8125rem; font-weight: 600; border-radius: var(--radius-sm, 4px); border: 1px solid var(--border, #cbd5e1); background: var(--bg-surface, #fff); color: var(--text, #1e293b); cursor: pointer;"
                    onchange="window.location.href=this.value">
                @foreach([10, 50, 100, 500] as $size)
                    <option value="{{ request()->fullUrlWithQuery(['per_page' => $size, $paginator->getPageName() => 1]) }}" @selected((int)request('per_page', $paginator->perPage()) === $size)>
                        {{ $size }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    @if($paginator->hasPages())
    <div style="display: inline-flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
        @if($paginator->onFirstPage())
            <span class="pagination-link disabled" aria-disabled="true" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; color: var(--text-muted, #94a3b8); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-sm, 4px); background: var(--bg-subtle, #f8fafc); cursor: not-allowed;">‹ Trước</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="pagination-link" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; text-decoration: none; color: var(--text, #334155); border: 1px solid var(--border, #cbd5e1); border-radius: var(--radius-sm, 4px); background: var(--surface, #fff);">‹ Trước</a>
        @endif

        @foreach($elements as $element)
            @if(is_string($element))
                <span class="pagination-ellipsis" style="padding: 0.35rem 0.5rem; font-size: 0.85rem; color: var(--text-muted, #94a3b8);">{{ $element }}</span>
            @else
                @foreach($element as $page => $url)
                    @if($page === $paginator->currentPage())
                        <strong aria-current="page" class="pagination-current" style="padding: 0.35rem 0.75rem; font-size: 0.85rem; font-weight: 700; color: #fff; background: var(--primary, #2563eb); border: 1px solid var(--primary, #2563eb); border-radius: var(--radius-sm, 4px);">{{ $page }}</strong>
                    @else
                        <a href="{{ $url }}" class="pagination-link" aria-label="Trang {{ $page }}" style="padding: 0.35rem 0.75rem; font-size: 0.85rem; text-decoration: none; color: var(--text, #334155); border: 1px solid var(--border, #cbd5e1); border-radius: var(--radius-sm, 4px); background: var(--surface, #fff);">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="pagination-link" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; text-decoration: none; color: var(--text, #334155); border: 1px solid var(--border, #cbd5e1); border-radius: var(--radius-sm, 4px); background: var(--surface, #fff);">Sau ›</a>
        @else
            <span class="pagination-link disabled" aria-disabled="true" style="padding: 0.35rem 0.65rem; font-size: 0.85rem; color: var(--text-muted, #94a3b8); border: 1px solid var(--border, #e2e8f0); border-radius: var(--radius-sm, 4px); background: var(--bg-subtle, #f8fafc); cursor: not-allowed;">Sau ›</span>
        @endif
    </div>
    @endif
</nav>
@endif

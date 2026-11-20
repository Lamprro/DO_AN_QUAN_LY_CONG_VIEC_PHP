@php($utilityPrefix=$project?'workspaces.projects':'workspaces')
{{-- Saved filters as chips; saving the current filter is one short inline form. --}}
<div class="saved-filters" data-saved-filters>
    <span class="saved-filters-label">⭐ Đã lưu:</span>
    @forelse($savedFilters as $saved)
        <span class="saved-filter-chip">
            <a href="{{ route($utilityPrefix.'.task-filters.open',[...$routeParams,$saved]) }}">{{ $saved->name }}</a>
            <form method="post" action="{{ route($utilityPrefix.'.task-filters.destroy',[...$routeParams,$saved]) }}" data-form="filter-delete" data-confirm="Xóa bộ lọc “{{ $saved->name }}”?">
                @csrf
                @method('DELETE')
                <button type="submit" class="saved-filter-remove" title="Xóa bộ lọc" aria-label="Xóa bộ lọc {{ $saved->name }}">✕</button>
            </form>
        </span>
    @empty
        <span class="muted">chưa có</span>
    @endforelse
    <details class="saved-filter-new" @if($errors->has('name') || $errors->has('query')) open @endif>
        <summary>+ Lưu bộ lọc hiện tại</summary>
        <form method="post" action="{{ route($utilityPrefix.'.task-filters.store',$routeParams) }}" data-form="filter-save">
            @csrf
            @foreach($filters as $key=>$value)
                <input type="hidden" name="query[{{ $key }}]" value="{{ $value }}">
            @endforeach
            @if(!$filters)
                <input type="hidden" name="query[preset]" value="all">
            @endif
            <label class="sr-only" for="filter-name">Tên bộ lọc</label>
            <input name="name" id="filter-name" maxlength="80" required placeholder="Tên bộ lọc (tối đa 20 bộ)">
            <button type="submit" class="secondary compact">Lưu</button>
        </form>
    </details>
</div>

@if(isset($groups) && $groups->isNotEmpty())
    @php
        $currentGroupId = (string)($filters['group_id'] ?? '');
        $viewType = $currentView ?? ($filters['view'] ?? 'board');
    @endphp
    <div class="jira-epic-bar" data-epic-bar role="group" aria-label="Lọc theo Nhóm (Epic)">
        <span class="jira-epic-label">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="vertical-align: -1px; margin-right: 2px;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
            Nhóm:
        </span>
        <a href="{{ route($routePrefix.'.index', array_merge($routeParams, collect($filters)->except('group_id')->all(), ['view' => $viewType])) }}"
           class="jira-epic-chip {{ $currentGroupId === '' ? 'active' : '' }}"
           @if($currentGroupId === '') aria-current="true" @endif>
            Tất cả nhóm
        </a>
        @foreach($groups as $g)
            @php($isSelected = $currentGroupId === (string)$g->id)
            <a href="{{ route($routePrefix.'.index', array_merge($routeParams, $filters, ['view' => $viewType, 'group_id' => $g->id])) }}"
               class="jira-epic-chip {{ $isSelected ? 'active' : '' }}"
               @if($isSelected) aria-current="true" @endif
               title="{{ $g->code }}: {{ $g->title }}">
                <span class="epic-chip-icon">📁</span>
                <span class="epic-chip-code">{{ $g->code }}</span>
                <span class="epic-chip-sep">·</span>
                <span class="epic-chip-title">{{ \Illuminate\Support\Str::limit($g->title, 24) }}</span>
            </a>
        @endforeach
    </div>
@endif

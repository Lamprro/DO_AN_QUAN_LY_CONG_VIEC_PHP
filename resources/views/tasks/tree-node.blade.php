<ul class="task-tree" style="list-style: none; padding-left: {{ $parentId === 'root' ? '0' : '1.75rem' }}; margin: 0; position: relative;">
    @forelse($nodes->get($parentId, collect()) as $node)
        @if(!in_array($node->id, $visited, true))
            <li data-tree-task-id="{{ $node->id }}" style="margin: 0.5rem 0; position: relative;">
                @if($node->task_type->value === 'GROUP')
                    <details open style="border: 1px solid var(--color-border); border-radius: var(--radius-sm); background: var(--color-surface); padding: 0.625rem 0.875rem;">
                        <summary style="font-weight: 600; cursor: pointer; display: flex; align-items: center; justify-content: space-between; outline: none; user-select: none;">
                            <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #f59e0b; flex-shrink: 0;"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                <span style="font-family: var(--font-mono); font-size: 0.8125rem; color: var(--color-primary); font-weight: 700;">{{ $node->code }}</span>
                                <span style="color: var(--color-text); font-size: 0.9375rem;">{{ $node->title }}</span>
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <x-status :value="$node->status" />
                                <a href="{{ route($routePrefix.'.show', [...$routeParams, $node]) }}" style="font-size: 0.75rem; color: var(--color-primary); padding: 0.1875rem 0.5rem; background: var(--color-surface-hover); border-radius: var(--radius-xs); border: 1px solid var(--color-border); text-decoration: none;">
                                    Mở nhóm
                                </a>
                            </span>
                        </summary>
                        <div style="margin-top: 0.625rem; padding-top: 0.625rem; border-top: 1px dashed var(--color-border);">
                            @if($nodes->has($node->id))
                                @include('tasks.tree-node', ['parentId' => $node->id, 'visited' => [...$visited, $node->id]])
                            @else
                                <p style="font-size: 0.8125rem; color: var(--color-text-muted); margin: 0.25rem 0 0.25rem 1rem;">(Nhóm chưa có công việc con)</p>
                            @endif
                        </div>
                    </details>
                @else
                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 0.875rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); gap: 0.75rem; transition: background-color var(--transition-fast);">
                        <div style="display: flex; align-items: center; gap: 0.625rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                            @if($node->task_type->value === 'MILESTONE')
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: #ec4899; flex-shrink: 0;"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                            @else
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--color-primary); flex-shrink: 0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            @endif
                            <a href="{{ route($routePrefix.'.show', [...$routeParams, $node]) }}" style="font-weight: 600; color: var(--color-text); text-decoration: none; font-size: 0.875rem;">
                                <span style="font-family: var(--font-mono); color: var(--color-primary); margin-right: 0.25rem;">{{ $node->code }}</span>
                                {{ $node->title }}
                            </a>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;">
                            <x-status :value="$node->task_type" />
                            <x-status :value="$node->status" />
                        </div>
                    </div>
                    @if($nodes->has($node->id))
                        @include('tasks.tree-node', ['parentId' => $node->id, 'visited' => [...$visited, $node->id]])
                    @endif
                @endif
            </li>
        @endif
    @empty
        <li style="font-size: 0.875rem; color: var(--color-text-muted); padding: 0.5rem 0;">Chưa có công việc phù hợp trong cây.</li>
    @endforelse
</ul>

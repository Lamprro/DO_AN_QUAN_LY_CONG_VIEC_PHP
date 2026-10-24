<x-layout title="Bảng Kanban công việc">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project?->name ?? $workspace->name }}</p>
        <h1 style="margin: 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em;">Bảng Kanban Công việc</h1>
        <p class="muted" style="margin-top: 0.25rem;">Theo dõi trạng thái trực quan, chỉnh sửa nhanh ngay trên thẻ</p>
    </div>
    <div class="actions" style="display: flex; gap: 0.5rem; align-items: center;">
        @if($canCreate)
            <button type="button" class="button" id="btn-trigger-quick-create" style="background: var(--primary); color: #fff; font-weight: 600; box-shadow: 0 2px 4px rgba(79, 70, 229, 0.25);">
                <span>⚡</span> Tạo nhanh
            </button>
            <a class="button secondary" href="{{ route($routePrefix.'.create',$routeParams) }}">
                <span>+</span> Chi tiết
            </a>
        @endif
    </div>
</div>

@include('tasks._view-nav')
@include('tasks._filters')

<p class="result-summary" role="status" style="margin-bottom: 1rem;">
    <strong>{{ collect($board)->sum(fn($column)=>$column->total()) }}</strong> công việc và cột mốc phù hợp
</p>

<div class="board-scroll" tabindex="0" role="region" aria-label="Các cột công việc; cuộn ngang để xem">
    <div class="task-board" data-page="task-board">
        @foreach($board as $status => $column)
            <section class="board-column" data-status="{{ $status }}" style="background: var(--surface-hover); border-radius: var(--radius-lg); padding: 1rem; min-width: 280px; max-width: 340px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 2px solid var(--border);">
                    <h2 style="font-size: 0.95rem; font-weight: 700; margin: 0; display: flex; align-items: center; gap: 0.4rem;">
                        <span class="status-pill status-{{ strtolower(str_replace('_','-',$status)) }}" style="padding: 0.15rem 0.45rem; font-size: 0.75rem;">
                            {{ \App\Support\UiLabels::text($status) }}
                        </span>
                    </h2>
                    <span class="badge" style="font-size: 0.75rem; font-weight: 700;">{{ $column->total() }}</span>
                </div>

                <div class="board-cards-wrap" style="display: flex; flex-direction: column; gap: 0.75rem;">
                    @forelse($column as $task)
                        @php($deadlineFormatted = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('d/m H:i') : null)
                        @php($deadlineLocal = $task->deadline_at ? $task->deadline_at->setTimezone($workspace->timezone)->format('Y-m-d\TH:i') : '')
                        
                        <article class="card board-card"
                                 data-task-id="{{ $task->id }}"
                                 data-code="{{ $task->code }}"
                                 data-title="{{ $task->title }}"
                                 data-status="{{ $task->status->value }}"
                                 data-priority="{{ $task->priority->value }}"
                                 data-assignee-id="{{ $task->assignee_id }}"
                                 data-reviewer-id="{{ $task->reviewer_id }}"
                                 data-deadline="{{ $deadlineLocal }}"
                                 data-estimate="{{ $task->estimate_minutes }}"
                                 data-revision="{{ $task->revision }}"
                                 data-update-url="{{ route($routePrefix.'.update', [...$routeParams, $task]) }}"
                                 data-status-url="{{ route($routePrefix.'.status.update', [...$routeParams, $task]) }}"
                                 style="margin: 0; padding: 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); transition: transform 0.15s ease, box-shadow 0.15s ease;">
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                                <small class="task-code muted" style="font-family: var(--font-mono); font-weight: 700; color: var(--primary);">{{ $task->code }}</small>
                                <span class="badge task-row-priority" style="font-size: 0.68rem;">
                                    {{ \App\Support\UiLabels::text($task->priority->value) }}
                                </span>
                            </div>

                            <h3 style="font-size: 0.95rem; font-weight: 700; margin: 0 0 0.5rem 0; line-height: 1.35;">
                                <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="text-decoration: none; color: inherit;" class="task-title-link">
                                    <span class="task-row-title">{{ $task->title }}</span>
                                </a>
                            </h3>

                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed var(--border); font-size: 0.8rem;">
                                <div class="task-row-assignee" style="display: flex; align-items: center; gap: 0.35rem;">
                                    @if($task->assignee)
                                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--primary-light); color: var(--primary); font-size: 0.7rem; font-weight: 700;">
                                            {{ mb_substr($task->assignee->name, 0, 1) }}
                                        </span>
                                        <span style="color: var(--text-muted); font-size: 0.78rem;">{{ $task->assignee->name }}</span>
                                    @else
                                        <span class="muted" style="font-style: italic; font-size: 0.75rem;">Chưa giao</span>
                                    @endif
                                </div>

                                <div class="task-row-deadline">
                                    @if($task->deadline_at)
                                        <span style="font-size: 0.75rem; font-family: var(--font-mono); {{ $task->deadline_at->isPast() && in_array($task->status->value, ['TODO', 'IN_PROGRESS', 'REVIEW']) ? 'color: var(--danger); font-weight: 700;' : 'color: var(--text-muted);' }}">
                                            ⏰ {{ $deadlineFormatted }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 0.6rem; padding-top: 0.4rem;">
                                <a href="{{ route($routePrefix.'.show',[...$routeParams,$task]) }}" style="font-size: 0.75rem; text-decoration: none; font-weight: 600; color: var(--primary);">
                                    Chi tiết ›
                                </a>
                                @can('update', $task)
                                    <button type="button" class="btn-quick-edit preset-chip" style="font-size: 0.72rem; padding: 0.15rem 0.4rem; cursor: pointer; background: var(--surface); color: var(--text);" title="Sửa nhanh thẻ này">
                                        ⚡ Sửa
                                    </button>
                                @endcan
                            </div>
                        </article>
                    @empty
                        <div style="text-align: center; padding: 1.5rem 0.5rem; color: var(--text-muted); font-size: 0.825rem;">
                            Không có công việc nào
                        </div>
                    @endforelse
                </div>

                <div style="margin-top: 0.75rem;">
                    {{ $column->links() }}
                </div>
            </section>
        @endforeach
    </div>
</div>

@include('tasks._quick-modal')
</x-layout>

<x-layout title="Lịch biểu & Sự kiện">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project ? $project->name : $workspace->name }}</p>
        <h1 style="margin: 0;">Lịch làm việc {{ $project ? '— '.$project->name : '' }}</h1>
        <p class="muted" style="margin-top: 0.25rem;">
            {{ $project ? 'Hiển thị các khung giờ và sự kiện thuộc project này theo quyền được cấp.' : 'Hiển thị toàn bộ lịch làm việc và khung giờ trong workspace bạn được phép xem.' }}
        </p>
    </div>
    <div class="actions" style="display: flex; gap: 8px; flex-wrap: wrap;">
        @if($project)
            <a class="button secondary" href="{{ route('workspaces.projects.timeline',[$workspace,$project]) }}">
                📊 Xem Timeline
            </a>
        @endif
        <button type="button" class="button secondary" onclick="document.getElementById('quick-meeting-box').toggleAttribute('hidden')">
            🤝 + Mời họp nhanh
        </button>
        @if($canCreate)
            <a class="button primary" href="{{ route('workspaces.calendar.create',[$workspace,'project_id'=>$project?->id]) }}">
                <span>+</span> Tạo sự kiện mới
            </a>
        @endif
    </div>
</div>

@if(!empty($pendingProposals) && $pendingProposals->isNotEmpty())
    <div class="notice info" style="margin-bottom: 1.25rem; border-left: 5px solid var(--primary); padding: 12px 16px; background: rgba(99, 102, 241, 0.05); border-radius: 8px;">
        <strong style="color: var(--primary); font-size: 0.95rem;">🔔 Bạn có {{ $pendingProposals->count() }} lời mời họp / đề xuất lịch:</strong>
        <div style="margin-top: 8px; display: flex; flex-direction: column; gap: 8px;">
            @foreach($pendingProposals as $prop)
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; background: var(--surface); padding: 8px 12px; border-radius: 6px; border: 1px solid var(--border);">
                    <div>
                        <strong style="color: var(--text);">{{ $prop->title }}</strong>
                        <span class="muted" style="font-size: 0.85rem;">(từ {{ $prop->proposer->name }}: {{ $prop->starts_at->setTimezone($range['timezone'])->format('d/m/Y H:i') }} → {{ $prop->ends_at->setTimezone($range['timezone'])->format('H:i') }})</span>
                    </div>
                    <div style="display: flex; gap: 6px;">
                        <form method="post" action="{{ route('workspaces.calendar.respond', [$workspace, $prop->id, 'accept']) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ app(\App\Services\Calendar\CalendarService::class)->version($prop) }}">
                            <button type="submit" class="button compact" style="font-size: 11px; padding: 3px 8px;">Chấp thuận</button>
                        </form>
                        <form method="post" action="{{ route('workspaces.calendar.respond', [$workspace, $prop->id, 'decline']) }}">
                            @csrf
                            <input type="hidden" name="version" value="{{ app(\App\Services\Calendar\CalendarService::class)->version($prop) }}">
                            <button type="submit" class="button secondary compact" style="font-size: 11px; padding: 3px 8px;">Từ chối</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- Hộp mời họp tích hợp ngay trên lịch --}}
<div id="quick-meeting-box" hidden style="margin-bottom: 1.5rem; background: var(--surface); border: 2px solid var(--primary); border-radius: 8px; padding: 1.25rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h3 style="margin: 0; font-size: 1.1rem; font-weight: 750; color: var(--text);">
            🤝 Tạo cuộc họp & Gửi lời mời trực tiếp
        </h3>
        <button type="button" class="button secondary compact" onclick="document.getElementById('quick-meeting-box').setAttribute('hidden', 'true')">✕ Đóng</button>
    </div>
    <form method="post" action="{{ route('workspaces.calendar.propose', $workspace) }}" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
        @csrf
        @if($project)
            <input type="hidden" name="project_id" value="{{ $project->id }}">
        @endif
        <div>
            <label for="meet-title" style="display: block; font-size: 0.8125rem; font-weight: 650; margin-bottom: 4px;">Tiêu đề cuộc họp</label>
            <input id="meet-title" name="title" required maxlength="160" placeholder="Ví dụ: Họp trao đổi tiến độ..." style="width: 100%; padding: 0.45rem 0.65rem; border-radius: 6px; border: 1px solid var(--border);">
        </div>
        <div>
            <label for="meet-user" style="display: block; font-size: 0.8125rem; font-weight: 650; margin-bottom: 4px;">Mời thành viên</label>
            <select id="meet-user" name="user_id" required style="width: 100%; padding: 0.45rem 0.65rem; border-radius: 6px; border: 1px solid var(--border);">
                @foreach($eligibleMembers ?? [] as $m)
                    @if($m->id !== auth()->id())
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                    @endif
                @endforeach
            </select>
        </div>
        <div>
            <label for="meet-start" style="display: block; font-size: 0.8125rem; font-weight: 650; margin-bottom: 4px;">Bắt đầu ({{ $range['timezone'] }})</label>
            <input id="meet-start" name="starts_at" type="datetime-local" required value="{{ now($range['timezone'])->addHour()->startOfHour()->format('Y-m-d\TH:i') }}" style="width: 100%; padding: 0.45rem 0.65rem; border-radius: 6px; border: 1px solid var(--border);">
        </div>
        <div>
            <label for="meet-end" style="display: block; font-size: 0.8125rem; font-weight: 650; margin-bottom: 4px;">Kết thúc ({{ $range['timezone'] }})</label>
            <input id="meet-end" name="ends_at" type="datetime-local" required value="{{ now($range['timezone'])->addHours(2)->startOfHour()->format('Y-m-d\TH:i') }}" style="width: 100%; padding: 0.45rem 0.65rem; border-radius: 6px; border: 1px solid var(--border);">
        </div>
        <div>
            <button type="submit" class="button primary" style="height: 38px;">Gửi lời mời họp</button>
        </div>
    </form>
</div>

<section class="card" data-page="calendar-index" style="margin-bottom: 1.5rem; padding: 1.25rem;">
    @include('calendar._range')
    <p class="muted" style="font-size: 0.8rem; margin: 0.75rem 0 0 0;">
        🔒 Khoảng giờ bận riêng tư (Private Busy) không hiển thị tiêu đề hoặc chi tiết nhiệm vụ để bảo vệ quyền riêng tư cá nhân.
    </p>
</section>

@if($range['mode']==='list')
    <div style="margin-top: 1rem;">
        @forelse($rows as $row)
            @include('calendar._row')
        @empty
            <section class="card" style="text-align: center; padding: 3rem 1.5rem;">
                <p style="font-size: 2rem; margin-bottom: 0.5rem;">📅</p>
                <h3>Không có sự kiện</h3>
                <p class="muted">Không có sự kiện nào trong phạm vi ngày và quyền hiện tại.</p>
            </section>
        @endforelse
    </div>
@elseif($range['mode'] === 'day')
    {{-- Chế độ xem Ngày / Hôm nay tối ưu bố cục --}}
    @php
        $targetDay = $range['days'][0] ?? $navigation['todayDate'];
        $dayCarbon = \Carbon\CarbonImmutable::parse($targetDay, $range['timezone']);
        $dayRows = collect($rows)->filter(fn($row) => $row['starts_at']->lt($dayCarbon->addDay()) && $row['ends_at']->gt($dayCarbon))->sortBy('starts_at');
        $isToday = $targetDay === $navigation['todayDate'];
    @endphp

    <div class="calendar-day-agenda-layout" style="display: grid; grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr); gap: 1.5rem; align-items: flex-start;">
        {{-- Cột trái: Lịch trình chi tiết trong ngày --}}
        <section class="card" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 0.85rem; margin-bottom: 1.25rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.25rem; font-weight: 750; color: var(--text); display: flex; align-items: center; gap: 8px;">
                        <span>📅 Lịch trình ngày {{ $dayCarbon->format('d/m/Y') }}</span>
                        @if($isToday)
                            <span class="badge" style="background: var(--primary); color: #fff; font-size: 0.75rem; font-weight: 700;">Hôm nay</span>
                        @endif
                    </h2>
                    <p class="muted" style="margin: 4px 0 0; font-size: 0.85rem;">
                        {{ ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][$dayCarbon->dayOfWeek] }} · Múi giờ: {{ $range['timezone'] }}
                    </p>
                </div>
                <div>
                    <span class="badge" style="font-size: 0.85rem; font-weight: 700;">{{ $dayRows->count() }} mục lịch</span>
                </div>
            </div>

            @if($dayRows->isEmpty())
                <div style="text-align: center; padding: 3rem 1rem;">
                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">☀️</div>
                    <h3 style="margin: 0 0 0.5rem; color: var(--text);">Không có lịch bận trong ngày</h3>
                    <p class="muted" style="margin: 0 0 1rem; font-size: 0.875rem;">Ngày này đang hoàn toàn trống. Bạn có thể thoải mái tập trung làm việc hoặc xếp lịch mới.</p>
                    @if($canCreate)
                        <a href="{{ route('workspaces.calendar.create', [$workspace, 'date' => $targetDay, 'project_id' => $project?->id]) }}" class="button primary">
                            + Xếp lịch sự kiện cho ngày này
                        </a>
                    @endif
                </div>
            @else
                <div class="daily-timeline-feed" style="display: flex; flex-direction: column; gap: 0.85rem;">
                    @foreach($dayRows as $row)
                        @php
                            $isTaskSpan = !empty($row['is_task_span']);
                            $typeColor = match($row['type']) {
                                'MEETING' => '#8b5cf6',
                                'TASK_BLOCK' => '#2563eb',
                                'BUSY' => '#ef4444',
                                default => '#64748b',
                            };
                        @endphp
                        <article style="border: 1px solid var(--border); border-left: 5px solid {{ $typeColor }}; border-radius: 8px; padding: 1rem 1.25rem; background: var(--surface); display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 240px;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 6px; flex-wrap: wrap;">
                                    <span class="badge" style="background: {{ $typeColor }}15; color: {{ $typeColor }}; font-weight: 750; font-size: 11px;">
                                        {{ $isTaskSpan ? '📋 CÔNG VIỆC DỰ ÁN' : \App\Support\UiLabels::text($row['type']) }}
                                    </span>
                                    <span style="font-size: 0.85rem; font-weight: 700; color: var(--text);">
                                        ⏰ {{ $row['starts_at']->format('H:i') }} → {{ $row['ends_at']->format('H:i') }}
                                        @if($row['all_day']) (Cả ngày) @endif
                                    </span>
                                    @if($row['status'])
                                        <span class="badge" style="font-size: 10px;">{{ \App\Support\UiLabels::text($row['status']) }}</span>
                                    @endif
                                </div>
                                <h3 style="margin: 0 0 6px; font-size: 1.05rem; font-weight: 700;">
                                    @if($row['id'])
                                        <a href="{{ route('workspaces.calendar.show', [$workspace, $row['id']]) }}" style="color: var(--text); text-decoration: none;">
                                            {{ $row['title'] }}
                                        </a>
                                    @elseif($row['task'])
                                        <a href="{{ route($row['task']->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $row['task']->project_id ? [$workspace, $row['task']->project, $row['task']] : [$workspace, $row['task']]) }}" style="color: var(--text); text-decoration: none;">
                                            {{ $row['title'] }}
                                        </a>
                                    @else
                                        {{ $row['title'] }}
                                    @endif
                                </h3>
                                @if(!empty($row['description']))
                                    <p style="margin: 0 0 6px; font-size: 0.875rem; color: var(--text-muted);">
                                        {{ $row['description'] }}
                                    </p>
                                @endif
                                <div style="font-size: 0.8125rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                                    @if($row['owner'])
                                        <span>👤 {{ $row['owner'] }}</span>
                                    @endif
                                    @if($row['task'])
                                        <span>· <a href="{{ route($row['task']->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $row['task']->project_id ? [$workspace, $row['task']->project, $row['task']] : [$workspace, $row['task']]) }}" style="font-weight: 650; color: var(--primary);">📋 Mở công việc {{ $row['task']->code }}</a></span>
                                    @endif
                                </div>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                @if($row['id'])
                                    <a href="{{ route('workspaces.calendar.show', [$workspace, $row['id']]) }}" class="button secondary compact" style="font-size: 11px; padding: 4px 8px;">Chi tiết</a>
                                @elseif($row['task'])
                                    <a href="{{ route($row['task']->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $row['task']->project_id ? [$workspace, $row['task']->project, $row['task']] : [$workspace, $row['task']]) }}" class="button secondary compact" style="font-size: 11px; padding: 4px 8px;">Xem task</a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Cột phải: Tổng quan ngày & Lời mời / Tạo nhanh --}}
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <section class="card" style="padding: 1.25rem;">
                <h3 style="margin: 0 0 1rem; font-size: 1rem; font-weight: 750; color: var(--text);">
                    📊 Tổng quan ngày {{ $dayCarbon->format('d/m') }}
                </h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div style="padding: 0.75rem; background: var(--bg-subtle); border-radius: 6px; text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: var(--primary);">{{ $dayRows->count() }}</span>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Tổng sự kiện</div>
                    </div>
                    <div style="padding: 0.75rem; background: var(--bg-subtle); border-radius: 6px; text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 800; color: #8b5cf6;">{{ $dayRows->where('type', 'MEETING')->count() }}</span>
                        <div style="font-size: 0.75rem; color: var(--text-muted);">Cuộc họp</div>
                    </div>
                </div>
                <div style="margin-top: 1.25rem; display: flex; flex-direction: column; gap: 0.5rem;">
                    @if($canCreate)
                        <a href="{{ route('workspaces.calendar.create', [$workspace, 'date' => $targetDay, 'project_id' => $project?->id]) }}" class="button primary" style="width: 100%; text-align: center; text-decoration: none;">
                            + Tạo sự kiện cho ngày này
                        </a>
                    @endif
                    <button type="button" class="button secondary" onclick="document.getElementById('quick-meeting-box').removeAttribute('hidden')" style="width: 100%; text-align: center;">
                        🤝 Mời họp trực tiếp
                    </button>
                </div>
            </section>
        </div>
    </div>
@else
    {{-- Chế độ xem Lịch Chuẩn Jira (Đồng bộ thống nhất cho cả Tuần & Tháng) --}}
    @php
        // 1. Phân nhóm các ngày thành các tuần (Mỗi tuần 7 cột: Thứ 2 -> Chủ nhật)
        $weeks = [];
        $currentWeek = [];
        $firstDayCarbon = \Carbon\CarbonImmutable::parse($range['days'][0], $range['timezone']);
        $firstDow = $firstDayCarbon->dayOfWeekIso; // 1 (Thứ 2) -> 7 (Chủ nhật)
        for ($p = 1; $p < $firstDow; $p++) {
            $currentWeek[$p] = null;
        }
        foreach ($range['days'] as $dayStr) {
            $d = \Carbon\CarbonImmutable::parse($dayStr, $range['timezone']);
            $dow = $d->dayOfWeekIso;
            $currentWeek[$dow] = $dayStr;
            if ($dow === 7) {
                $weeks[] = $currentWeek;
                $currentWeek = [];
            }
        }
        if (!empty($currentWeek)) {
            for ($p = count($currentWeek) + 1; $p <= 7; $p++) {
                $currentWeek[$p] = null;
            }
            $weeks[] = $currentWeek;
        }
    @endphp

    <div class="jira-calendar-board" role="region" aria-label="Lưới lịch làm việc Jira" style="border: 1px solid var(--border); border-radius: 8px; background: var(--surface); box-shadow: var(--shadow-sm); overflow: hidden; margin-top: 1rem;">
        {{-- Hàng tiêu đề 7 thứ trong tuần --}}
        <div class="jira-calendar-header" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background: var(--bg-subtle, #f8fafc); border-bottom: 1px solid var(--border); text-align: center;">
            @foreach(['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ nhật'] as $dayName)
                <div style="padding: 10px 4px; font-size: 0.8rem; font-weight: 750; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-right: 1px solid var(--border-light, #f1f5f9);">
                    {{ $dayName }}
                </div>
            @endforeach
        </div>

        {{-- Danh sách các hàng Tuần (Week Rows) --}}
        @foreach($weeks as $weekIdx => $weekDays)
            @php
                $validDays = array_filter($weekDays);
                $wStartStr = !empty($validDays) ? reset($validDays) : null;
                $wEndStr = !empty($validDays) ? end($validDays) : null;
                $wStart = $wStartStr ? \Carbon\CarbonImmutable::parse($wStartStr, $range['timezone'])->startOfDay() : null;
                $wEnd = $wEndStr ? \Carbon\CarbonImmutable::parse($wEndStr, $range['timezone'])->endOfDay() : null;

                // Các công việc kéo dài nhiều ngày đi qua tuần này
                $weekMultiTasks = collect();
                if ($wStart && $wEnd) {
                    $weekMultiTasks = collect($rows)->where('is_task_span', true)->filter(function($r) use ($wStart, $wEnd) {
                        $isMulti = $r['starts_at']->format('Y-m-d') !== $r['ends_at']->format('Y-m-d');
                        if (!$isMulti) return false;
                        return $r['starts_at']->lte($wEnd) && $r['ends_at']->gte($wStart);
                    })->unique(fn($r) => $r['task']?->id ?? $r['title']);
                }
            @endphp

            <div class="jira-calendar-week-row" style="border-bottom: 1px solid var(--border); display: flex; flex-direction: column;">
                {{-- 1. Hàng số ngày trong tuần --}}
                <div class="jira-week-days-header" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-bottom: 1px solid var(--border-light, #f1f5f9);">
                    @for($col = 1; $col <= 7; $col++)
                        @php
                            $dayVal = $weekDays[$col] ?? null;
                            $dCarbon = $dayVal ? \Carbon\CarbonImmutable::parse($dayVal, $range['timezone']) : null;
                            $isToday = $dayVal && ($dayVal === $navigation['todayDate']);
                        @endphp
                        <div class="jira-day-header-cell" style="padding: 6px 8px; border-right: 1px solid var(--border-light, #f1f5f9); border-top: none; border-bottom: none; border-left: none; outline: none; display: flex; align-items: center; justify-content: space-between;">
                            @if($dCarbon)
                                <div style="display: flex; align-items: center; gap: 6px;">
                                    @if($isToday)
                                        <span class="jira-today-badge" style="background: #0f172a; color: #ffffff !important; border-radius: 4px; padding: 2px 7px; font-size: 11.5px; font-weight: 800; display: inline-flex; align-items: center; gap: 4px;">
                                            <span>{{ $dCarbon->format('d') }}</span>
                                            <span style="font-size: 9.5px; font-weight: 700; opacity: 0.9;">Hôm nay</span>
                                        </span>
                                    @else
                                        <a href="{{ request()->fullUrlWithQuery(['view'=>'day','date'=>$dayVal,'from'=>null,'to'=>null]) }}" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-decoration: none;">
                                            {{ $dCarbon->format('d') }}
                                        </a>
                                    @endif
                                    @if($range['mode'] === 'week')
                                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">tháng {{ $dCarbon->format('m') }}</span>
                                    @endif
                                </div>
                                @if($canCreate)
                                    <a href="{{ route('workspaces.calendar.create', [$workspace, 'date' => $dayVal, 'project_id' => $project?->id]) }}" class="jira-quick-add-btn" title="Tạo sự kiện ngày {{ $dCarbon->format('d/m') }}" style="color: var(--text-muted); font-size: 13px; font-weight: 700; text-decoration: none; opacity: 0.6; padding: 0 4px; border-radius: 3px;">+</a>
                                @endif
                            @else
                                <span style="font-size: 11px; color: var(--text-muted); opacity: 0.3;">--</span>
                            @endif
                        </div>
                    @endfor
                </div>

                {{-- 2. Khung các thanh công việc kéo dài ngang chuẩn Jira (Spanning Task Bars) --}}
                @if($weekMultiTasks->isNotEmpty())
                    <div class="jira-week-spanning-bars" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 3px 0; padding: 4px 6px; background: var(--bg-subtle, #fcfcfd); border-bottom: 1px solid var(--border-light, #f1f5f9);">
                        @foreach($weekMultiTasks as $mTask)
                            @php
                                $mStart = $mTask['starts_at'];
                                $mEnd = $mTask['ends_at'];
                                $startCol = $mStart->lt($wStart) ? 1 : $mStart->dayOfWeekIso;
                                $endCol = $mEnd->gt($wEnd) ? 7 : $mEnd->dayOfWeekIso;
                                $colSpan = max(1, $endCol - $startCol + 1);
                                $taskRoute = $mTask['task'] ? route($mTask['task']->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $mTask['task']->project_id ? [$workspace, $mTask['task']->project, $mTask['task']] : [$workspace, $mTask['task']]) : '#';
                            @endphp
                            <div class="jira-task-span-bar" style="grid-column: {{ $startCol }} / span {{ $colSpan }}; background: #1e3a8a; border: 1px solid rgba(59, 130, 246, 0.4); border-radius: 4px; height: 26px; padding: 0 8px; margin: 1px 3px; display: flex; align-items: center; justify-content: space-between; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; box-shadow: 0 1px 3px rgba(0,0,0,0.12); cursor: pointer;" title="{{ $mTask['title'] }} ({{ $mStart->format('d/m H:i') }} → {{ $mEnd->format('d/m H:i') }})">
                                <div style="display: flex; align-items: center; gap: 6px; min-width: 0; overflow: hidden; text-overflow: ellipsis;">
                                    <span style="font-size: 11px; flex-shrink: 0;">📋</span>
                                    @if($mTask['task'])
                                        <span class="jira-badge-code" style="background: rgba(255, 255, 255, 0.2); color: #ffffff !important; padding: 1px 5px; border-radius: 3px; font-weight: 800; font-size: 11px; flex-shrink: 0;">
                                            {{ $mTask['task']->code }}
                                        </span>
                                    @endif
                                    <a href="{{ $taskRoute }}" style="color: #ffffff !important; font-weight: 650; font-size: 11.5px; text-decoration: none !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        {{ $mTask['task'] ? $mTask['task']->title : $mTask['title'] }}
                                    </a>
                                </div>
                                <div style="font-size: 10.5px; font-weight: 600; color: #ffffff !important; opacity: 0.95; white-space: nowrap; display: flex; align-items: center; gap: 4px; margin-left: 8px; flex-shrink: 0;">
                                    <span>{{ $mStart->format('d/m') }}</span>
                                    <span>→</span>
                                    <span>{{ $mEnd->format('d/m') }}</span>
                                    <span style="background: rgba(255,255,255,0.22); color: #ffffff !important; padding: 0 4px; border-radius: 3px; font-size: 9.5px;">{{ $colSpan }}d</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- 3. Các ô ngày bên dưới (Hiển thị sự kiện đơn lẻ & task trong ngày, không lặp lại task kéo dài) --}}
                <div class="jira-week-days-body" style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); {{ $range['mode'] === 'week' ? 'min-height: 220px;' : 'min-height: 85px;' }}">
                    @for($col = 1; $col <= 7; $col++)
                        @php
                            $dayVal = $weekDays[$col] ?? null;
                            $dCarbon = $dayVal ? \Carbon\CarbonImmutable::parse($dayVal, $range['timezone']) : null;
                            $isToday = $dayVal && ($dayVal === $navigation['todayDate']);

                            // Chỉ lấy các sự kiện hoặc task đơn ngày, TUYỆT ĐỐI không lặp lại task kéo dài đã có thanh ở trên
                            $singleItems = collect();
                            if ($dayVal) {
                                $singleItems = collect($rows)->filter(function($r) use ($dCarbon) {
                                    $isMulti = ($r['is_task_span'] ?? false) && ($r['starts_at']->format('Y-m-d') !== $r['ends_at']->format('Y-m-d'));
                                    if ($isMulti) return false;
                                    return $r['starts_at']->lt($dCarbon->addDay()) && $r['ends_at']->gt($dCarbon);
                                });
                            }
                        @endphp
                        <div class="jira-day-body-cell" style="padding: 6px; border-right: 1px solid var(--border-light, #f1f5f9); border-top: none; border-bottom: none; border-left: none; outline: none; display: flex; flex-direction: column; gap: 4px;">
                            @if($dayVal)
                                @forelse($singleItems as $item)
                                    @php
                                        $isTask = !empty($item['is_task_span']);
                                        $itemRoute = $item['id'] ? route('workspaces.calendar.show', [$workspace, $item['id']]) : ($item['task'] ? route($item['task']->project_id ? 'workspaces.projects.tasks.show' : 'workspaces.tasks.show', $item['task']->project_id ? [$workspace, $item['task']->project, $item['task']] : [$workspace, $item['task']]) : '#');
                                        $barColor = $isTask ? '#2563eb' : match($item['type']) {
                                            'MEETING' => '#8b5cf6',
                                            'BUSY' => '#ef4444',
                                            default => '#0284c7',
                                        };
                                    @endphp
                                    <div class="jira-single-event-card" style="background: var(--surface); border: 1px solid var(--border); border-left: 3px solid {{ $barColor }}; border-radius: 4px; padding: 4px 6px; font-size: 11px; box-shadow: 0 1px 2px rgba(0,0,0,0.03);" title="{{ $item['title'] }} ({{ $item['starts_at']->format('H:i') }} → {{ $item['ends_at']->format('H:i') }})">
                                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px;">
                                            <a href="{{ $itemRoute }}" style="color: var(--text) !important; font-weight: 650; text-decoration: none !important; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                @if($isTask)
                                                    📋 {{ $item['task']->code ?? '' }}: {{ $item['task']->title ?? $item['title'] }}
                                                @else
                                                    {{ $item['type'] === 'MEETING' ? '🤝' : '📅' }} {{ $item['title'] }}
                                                @endif
                                            </a>
                                            <span style="font-size: 10px; color: var(--text-muted); font-weight: 600; white-space: nowrap;">
                                                {{ $item['starts_at']->format('H:i') }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    {{-- Ngày trống không có sự kiện đơn lẻ --}}
                                @endforelse
                            @else
                                {{-- Ô padding ngày của tháng khác --}}
                                <div style="height: 100%; background: var(--bg-subtle, #f8fafc); opacity: 0.4;"></div>
                            @endif
                        </div>
                    @endfor
                </div>
            </div>
        @endforeach
    </div>
@endif
</x-layout>

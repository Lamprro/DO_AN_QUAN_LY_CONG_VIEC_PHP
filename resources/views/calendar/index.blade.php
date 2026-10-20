<x-layout title="Lịch biểu & Sự kiện">
<div class="page-heading" style="margin-bottom: 1.25rem;">
    <div>
        <p class="eyebrow">{{ $project ? $project->name : $workspace->name }}</p>
        <h1 style="margin: 0;">Lịch làm việc {{ $project ? '— '.$project->name : '' }}</h1>
        <p class="muted" style="margin-top: 0.25rem;">
            {{ $project ? 'Hiển thị các khung giờ và sự kiện thuộc project này theo quyền được cấp.' : 'Hiển thị toàn bộ lịch làm việc và khung giờ trong workspace bạn được phép xem.' }}
        </p>
    </div>
    <div class="actions">
        @if($project)
            <a class="button secondary" href="{{ route('workspaces.projects.timeline',[$workspace,$project]) }}">
                📊 Xem Timeline
            </a>
        @endif
        @if($canCreate)
            <a class="button" href="{{ route('workspaces.calendar.create',[$workspace,'project_id'=>$project?->id]) }}">
                <span>+</span> Tạo sự kiện mới
            </a>
        @endif
    </div>
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
@else
    <div class="calendar-grid-scroll" role="region" aria-label="Lưới lịch theo ngày" tabindex="0" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); overflow-x: auto;">
        <div class="calendar-grid calendar-mode-{{ $range['mode'] }}">
            @if($range['mode']==='month')
                @foreach(['Thứ 2','Thứ 3','Thứ 4','Thứ 5','Thứ 6','Thứ 7','Chủ nhật'] as $label)
                    <div class="weekday-label" style="text-align: center; font-size: 0.8rem; font-weight: 750; color: var(--text-muted); text-transform: uppercase;">
                        {{ $label }}
                    </div>
                @endforeach
                @for($pad=1;$pad<$range['start']->dayOfWeekIso;$pad++)
                    <div class="calendar-empty" aria-hidden="true" style="background: var(--bg-subtle); border-radius: var(--radius-sm); min-height: 100px;"></div>
                @endfor
            @endif

            @foreach($range['days'] as $day)
                <section class="card calendar-day {{ $day===$navigation['todayDate']?'is-today':'' }}" data-day="{{ $day }}">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <h2 style="margin: 0; font-size: 0.9rem;">
                            <a href="{{ request()->fullUrlWithQuery(['view'=>'day','date'=>$day,'from'=>null,'to'=>null]) }}" style="font-weight: 700;">
                                {{ \Carbon\CarbonImmutable::parse($day)->format('d/m') }}
                            </a>
                        </h2>
                        @if($day===$navigation['todayDate'])
                            <span class="badge" style="background: var(--primary); color: #fff; font-size: 0.65rem; padding: 0.1rem 0.4rem; border: none;">
                                Hôm nay
                            </span>
                        @endif
                    </div>

                    @php($dayRows=collect($rows)->filter(fn($row)=>$row['starts_at']->lt(\Carbon\CarbonImmutable::parse($day,$range['timezone'])->addDay())&&$row['ends_at']->gt(\Carbon\CarbonImmutable::parse($day,$range['timezone']))))
                    @forelse($dayRows as $row)
                        @include('calendar._row')
                    @empty
                        <p class="muted" style="font-size: 0.75rem; font-style: italic; margin: 0.5rem 0;">Không có sự kiện</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
@endif
</x-layout>

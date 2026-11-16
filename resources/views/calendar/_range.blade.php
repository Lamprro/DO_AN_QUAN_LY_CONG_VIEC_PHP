<div class="calendar-toolbar" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem;">
    {{-- Left: Navigation buttons and Period Heading --}}
    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
        <div class="actions calendar-navigation" aria-label="Điều hướng thời gian" style="margin: 0;">
            <a class="button secondary compact" href="{{ $navigation['previous'] }}">‹ Trước</a>
            <a class="button secondary compact" href="{{ $navigation['today'] }}">Hôm nay</a>
            <a class="button secondary compact" href="{{ $navigation['next'] }}">Sau ›</a>
        </div>
        <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: var(--text);">
            @if(filled(request('from')) && filled(request('to')))
                Khoảng ngày: {{ \Carbon\CarbonImmutable::parse(request('from'))->format('d/m/Y') }} → {{ \Carbon\CarbonImmutable::parse(request('to'))->format('d/m/Y') }}
            @elseif($range['mode'] === 'week')
                Tuần {{ $range['start']->format('d/m') }} – {{ $range['end']->subDay()->format('d/m/Y') }}
            @elseif($range['mode'] === 'month')
                Tháng {{ $range['start']->format('m/Y') }}
            @elseif($range['mode'] === 'day')
                Ngày {{ $range['start']->format('d/m/Y') }}
            @else
                {{ $range['start']->format('d/m/Y') }} – {{ $range['end']->format('d/m/Y') }}
            @endif
        </h2>
    </div>

    {{-- Right: View Switcher (Tuần mặc định, Tháng, Ngày, Danh sách) --}}
    <div class="calendar-view-tabs" style="display: inline-flex; border-radius: var(--radius-md, 8px); border: 1px solid var(--border); overflow: hidden; background: var(--surface);">
        @foreach(['week' => '📅 Theo Tuần', 'month' => '🗓️ Theo Tháng', 'day' => '☀️ Theo Ngày', 'list' => '📋 Danh sách'] as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['view' => $key, 'from' => null, 'to' => null]) }}"
               style="padding: 0.5rem 0.85rem; font-size: 0.825rem; font-weight: 650; text-decoration: none; border-right: 1px solid var(--border); transition: all 0.15s; {{ $range['mode'] === $key && !filled(request('from')) ? 'background: var(--primary); color: #fff;' : 'background: transparent; color: var(--text);' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

{{-- Custom Date Range & Filter Options --}}
<details class="calendar-custom-range" @if(filled(request('from')) || filled(request('to')) || $range['includeCancelled']) open @endif style="margin-bottom: 1rem; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 0.85rem 1rem; background: var(--bg-subtle);">
    <summary style="cursor: pointer; font-size: 0.875rem; font-weight: 650; color: var(--text);">
        🔍 Tùy chọn xem theo khoảng thời gian tùy chỉnh & trạng thái
    </summary>
    <form method="GET" data-form="calendar-range" class="range-form" style="margin-top: 0.85rem; display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
        @if(request('project_id'))
            <input type="hidden" name="project_id" value="{{ request('project_id') }}">
        @endif
        <input type="hidden" name="view" value="{{ $range['mode'] }}">

        <div>
            <label for="calendar-from" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Từ ngày</label>
            <input id="calendar-from" name="from" type="date" value="{{ request('from', '') }}" style="padding: 0.4rem 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        </div>

        <div>
            <label for="calendar-to" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Đến ngày (không gồm)</label>
            <input id="calendar-to" name="to" type="date" value="{{ request('to', '') }}" style="padding: 0.4rem 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        </div>

        <div>
            <label for="calendar-date" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.25rem;">Hoặc ngày tham chiếu</label>
            <input id="calendar-date" name="date" type="date" value="{{ $range['start']->format('Y-m-d') }}" style="padding: 0.4rem 0.65rem; border-radius: var(--radius-sm); border: 1px solid var(--border);">
        </div>

        <div style="display: flex; align-items: center; gap: 0.4rem; padding-bottom: 0.5rem;">
            <input type="checkbox" id="include_cancelled" name="include_cancelled" value="1" @checked($range['includeCancelled'])>
            <label for="include_cancelled" style="margin: 0; font-size: 0.85rem; font-weight: 500; cursor: pointer;">Hiện sự kiện đã hủy</label>
        </div>

        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <button type="submit" class="button compact">Lọc khoảng ngày</button>
            <a href="{{ request()->fullUrlWithQuery(['from' => null, 'to' => null, 'date' => null, 'view' => 'week']) }}" class="button secondary compact">Về tuần mặc định</a>
        </div>
    </form>
    <p class="muted" style="font-size: 0.775rem; margin: 0.5rem 0 0 0;">
        Phạm vi ngày tùy chỉnh tối đa 93 ngày. Múi giờ hiển thị: <strong>{{ $range['timezone'] }}</strong>.
    </p>
</details>

{{-- Legend --}}
<div class="calendar-legend" aria-label="Chú giải loại sự kiện" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; margin-top: 0.5rem;">
    <span style="font-size: 0.775rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Chú giải:</span>
    @foreach(['TASK_BLOCK' => 'Khung giờ task', 'MEETING' => 'Cuộc họp', 'BUSY' => 'Bận', 'NOTE' => 'Ghi chú', 'EXTRA_AVAILABLE' => 'Khung giờ thêm'] as $type => $label)
        <span class="event-kind kind-{{ strtolower($type) }}" style="font-size: 0.75rem; padding: 2px 8px; border-radius: 4px; border: 1px solid var(--border);">
            {{ $label }}
        </span>
    @endforeach
</div>

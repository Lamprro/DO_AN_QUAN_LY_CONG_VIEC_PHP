<x-layout :title="$event ? 'Sửa sự kiện' : 'Tạo sự kiện'">
<div style="max-width: 860px; margin: 0 auto 2rem;">
    <div class="breadcrumb" style="font-size: 0.8125rem; color: var(--color-text-muted); margin-bottom: 0.5rem;">
        <a href="{{ route('workspaces.calendar.index', $workspace) }}" style="color: var(--color-primary);">Lịch</a>
        <span style="margin: 0 0.375rem;">/</span>
        <span>{{ $event ? 'Chỉnh sửa sự kiện' : 'Tạo sự kiện mới' }}</span>
    </div>

    <section class="card" data-page="calendar-form" style="padding: 2rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--color-text);">
                {{ $event ? 'Sửa sự kiện: ' . $event->title : 'Tạo sự kiện lịch mới' }}
            </h1>
            @php($allDay = (bool) old('all_day', request('all_day', $event?->all_day ?? false)))
            @php($zone = $event?->timezone ?? auth()->user()->timezone)
            <div style="display: inline-flex; background: var(--color-surface-hover); padding: 0.25rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border);">
                <a href="{{ request()->fullUrlWithQuery(['all_day' => 0]) }}" class="button {{ !$allDay ? '' : 'secondary' }}" style="padding: 0.3125rem 0.75rem; font-size: 0.8125rem; text-decoration: none; border-radius: var(--radius-xs);">
                    Giờ cụ thể
                </a>
                <a href="{{ request()->fullUrlWithQuery(['all_day' => 1]) }}" class="button {{ $allDay ? '' : 'secondary' }}" style="padding: 0.3125rem 0.75rem; font-size: 0.8125rem; text-decoration: none; border-radius: var(--radius-xs);">
                    Cả ngày
                </a>
            </div>
        </div>

        <form method="POST" action="{{ $event ? route('workspaces.calendar.update', [$workspace, $event]) : route('workspaces.calendar.store', $workspace) }}" data-form="calendar-save" data-dirty-form style="display: flex; flex-direction: column; gap: 1.25rem;">
            @csrf
            @if($event)
                @method('PATCH')
                <input type="hidden" name="version" value="{{ old('version',$version) }}">
                <a href="{{ route('workspaces.calendar.edit',[$workspace,$event,'fresh'=>1]) }}">Nạp dữ liệu hiện tại (bỏ bản nhập chưa lưu)</a>
            @endif
            <input type="hidden" name="all_day" value="{{ $allDay ? 1 : 0 }}">

            <x-input name="title" label="Tiêu đề sự kiện" :value="$event?->title ?? ($prefill['title'] ?? '')" required maxlength="200" placeholder="Ví dụ: Họp Sprint Review, Lập kế hoạch dự án..." />

            <x-textarea name="description" label="Nội dung chi tiết" :value="$event?->description ?? ''" placeholder="Mục tiêu buổi họp, link meet, tài liệu đính kèm..." />

            @if($event)
                <input type="hidden" name="event_type" value="{{ $event->event_type->value }}">
                <input type="hidden" name="project_id" value="{{ $event->project_id }}">
                <input type="hidden" name="task_id" value="{{ $event->task_id }}">
            @endif

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 1rem;">
                <div class="field">
                    <label for="event_type" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Loại sự kiện</label>
                    <select id="event_type" name="event_type" @disabled($event) style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        @foreach(\App\Enums\CalendarEventType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('event_type', $event?->event_type->value ?? ($prefill['event_type'] ?? 'NOTE')) === $type->value)>
                                {{ \App\Support\UiLabels::text($type) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="project_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Dự án liên kết</label>
                    <select name="project_id" id="project_id" @disabled($event) style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        <option value="">Không liên kết (Lịch chung workspace)</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id', $event?->project_id ?? ($prefill['project_id'] ?? null)) === $project->id)>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="task_id" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Công việc gán khối (chỉ áp dụng cho TASK_BLOCK)</label>
                <select name="task_id" id="task_id" @disabled($event) style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    <option value="">Không gán công việc</option>
                    @foreach($tasks as $task)
                        <option value="{{ $task->id }}" data-project-id="{{ $task->project_id }}" @selected(old('task_id', $event?->task_id ?? ($prefill['task_id'] ?? null)) === $task->id)>
                            {{ $task->code }} · {{ $task->title }}
                        </option>
                    @endforeach
                </select>
                <p class="muted" style="margin: 0.375rem 0 0; font-size: 0.75rem; color: var(--color-text-muted);">
                    ℹ️ Chọn task cùng project. Loại, project và task không thể thay đổi sau khi tạo.
                </p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap: 1rem;">
                <div class="field">
                    <label for="timezone" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Múi giờ</label>
                    <select name="timezone" id="timezone" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        @foreach($timezones as $timezone)
                            <option value="{{ $timezone }}" @selected(old('timezone', $zone) === $timezone)>
                                {{ $timezone }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <x-input name="starts_at" label="Thời gian bắt đầu" :type="$allDay ? 'date' : 'datetime-local'" :value="$event?->starts_at->setTimezone($zone)->format($allDay ? 'Y-m-d' : 'Y-m-d\TH:i') ?? ''" required />

                <x-input name="ends_at" label="Thời gian kết thúc" :type="$allDay ? 'date' : 'datetime-local'" :value="$event?->ends_at->setTimezone($zone)->format($allDay ? 'Y-m-d' : 'Y-m-d\TH:i') ?? ''" required />
            </div>

            <div style="background: var(--color-surface-hover); border: 1px solid var(--color-border); border-radius: var(--radius-sm); padding: 0.875rem 1rem;">
                <input type="hidden" name="is_busy" value="0">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.875rem; font-weight: 500; color: var(--color-text); margin: 0;">
                    <input type="checkbox" name="is_busy" value="1" @checked(old('is_busy', $event?->is_busy ?? false))>
                    <span>Đánh dấu là thời gian <strong>BẬN (Busy)</strong></span>
                </label>
                <div style="font-size: 0.75rem; color: var(--color-text-muted); margin-top: 0.25rem;">
                    (NOTE là tùy chọn; MEETING/BUSY/TASK_BLOCK luôn bận; EXTRA_AVAILABLE luôn không bận)
                </div>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); gap: 1rem;">
                <div class="field">
                    <label for="visibility" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Phạm vi hiển thị</label>
                    <select id="visibility" name="visibility" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        @foreach(['private' => 'Riêng tư (Chỉ mình tôi)', 'workspace' => 'Toàn bộ Workspace', 'project' => 'Trong Project'] as $key => $text)
                            <option value="{{ $key }}" @selected(old('visibility', $event?->visibility->value ?? 'private') === $key)>
                                {{ $text }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <label for="status" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">Trạng thái</label>
                    <select id="status" name="status" style="width: 100%; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                        @foreach(['planned', 'completed', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(old('status', $event?->status->value ?? 'planned') === $status)>
                                {{ \App\Support\UiLabels::text($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field">
                <label for="participants" style="display: block; font-size: 0.8125rem; font-weight: 600; margin-bottom: 0.375rem; color: var(--color-text);">
                    Người tham gia tham khảo (tối đa 20 người)
                </label>
                <select multiple name="participants[]" id="participants" style="width: 100%; min-height: 100px; padding: 0.5rem 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--color-border); background: var(--color-surface); color: var(--color-text);">
                    @php($selected = old('participants', $event?->participants ?? []))
                    @foreach($members as $member)
                        <option value="{{ $member->user_id }}" @selected(is_array($selected) && in_array($member->user_id, $selected, true))>
                            {{ $member->user->name }} ({{ $member->user->email }})
                        </option>
                    @endforeach
                </select>
                <p class="muted" style="margin: 0.375rem 0 0; font-size: 0.75rem; color: var(--color-text-muted);">
                    Giữ phím Ctrl (hoặc Cmd trên Mac) để chọn nhiều người. Danh sách chỉ ghi người tham khảo; không đặt lịch thay hoặc gửi thông báo cho họ.
                </p>
            </div>

            <div class="actions" style="display: flex; gap: 0.75rem; align-items: center; margin-top: 1rem; padding-top: 1.25rem; border-top: 1px solid var(--color-border);">
                <button type="submit" class="button" style="padding: 0.625rem 1.5rem; font-weight: 600;">
                    {{ $event ? 'Lưu thay đổi' : 'Tạo sự kiện' }}
                </button>
                <a class="button secondary" href="{{ route('workspaces.calendar.index', $workspace) }}">
                    Hủy bỏ
                </a>
            </div>
        </form>
    </section>
</div>
</x-layout>

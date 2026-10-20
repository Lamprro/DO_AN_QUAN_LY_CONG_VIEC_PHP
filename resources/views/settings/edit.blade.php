<x-layout title="Cấu hình hệ thống">
<div class="page-heading" style="margin-bottom: 1.5rem;">
    <div>
        <p class="eyebrow">PREFERENCES</p>
        <h1 style="margin: 0;">Cấu hình cá nhân & Tùy chọn</h1>
        <p class="muted" style="margin-top: 0.25rem;">Tùy chỉnh giao diện, ngôn ngữ hiển thị và kênh thông báo</p>
    </div>
</div>

<div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(min(100%, 360px), 1fr)) !important; gap: 1.5rem;">
    <section class="card" data-page="settings-edit" style="margin: 0; border-top: 4px solid var(--primary);">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">⚙️ Tùy chọn hiển thị & Thông báo</h2>

        <form method="POST" action="{{ route('settings.update') }}" data-form="settings-update">
            @csrf
            @method('PATCH')

            <div class="field">
                <label for="language">Ngôn ngữ ưu tiên</label>
                <select id="language" name="language">
                    @foreach(['vi'=>'Tiếng Việt','en'=>'English'] as $key=>$text)
                        <option value="{{ $key }}" @selected(old('language',$settings['language'])===$key)>{{ $text }}</option>
                    @endforeach
                </select>
                <p class="muted" style="font-size: 0.8rem; margin-top: 0.25rem;">Giao diện hiện tại được tối ưu hóa cho Tiếng Việt.</p>
            </div>

            <div class="field">
                <label for="theme">Chủ đề giao diện (Theme)</label>
                <select id="theme" name="theme">
                    @foreach(['system'=>'🌓 Theo thiết bị (System)','light'=>'☀️ Giao diện sáng (Light)','dark'=>'🌙 Giao diện tối (Dark)'] as $key=>$text)
                        <option value="{{ $key }}" @selected(old('theme',$settings['theme'])===$key)>{{ $text }}</option>
                    @endforeach
                </select>
            </div>

            <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin: 1.25rem 0;">
                <input type="hidden" name="notifications[in_app]" value="0">
                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; margin: 0; font-weight: 600;">
                    <input type="checkbox" name="notifications[in_app]" value="1" @checked(old('notifications.in_app',$settings['notifications']['in_app']))>
                    <span>Nhận thông báo trong ứng dụng (In-app Notifications)</span>
                </label>
                <p class="muted" style="font-size: 0.775rem; margin: 0.4rem 0 0 1.6rem;">
                    Email và Telegram chưa kết nối trong môi trường này. Tùy chọn in-app sẽ kiểm soát thông báo tại thanh điều hướng.
                </p>
            </div>

            <button type="submit">Lưu cấu hình</button>
        </form>
    </section>

    <section class="card" style="margin: 0; border-top: 4px solid #0284c7;">
        <h2 style="font-size: 1.25rem; margin-bottom: 0.5rem;">🕒 Khung giờ làm việc mặc định</h2>
        <p class="muted" style="font-size: 0.85rem; margin-bottom: 1rem;">
            Múi giờ hệ thống: <strong>{{ $schedule['timezone'] }}</strong> · Slot: <strong>{{ $schedule['slot_minutes'] }} phút</strong>
        </p>

        <div style="background: var(--bg-subtle); padding: 1rem 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border-light); margin-bottom: 1rem;">
            <p style="margin: 0 0 0.5rem 0; font-weight: 650; font-size: 0.85rem;">Các ngày làm việc trong tuần:</p>
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                @foreach([1=>'Thứ 2', 2=>'Thứ 3', 3=>'Thứ 4', 4=>'Thứ 5', 5=>'Thứ 6', 6=>'Thứ 7', 7=>'Chủ nhật'] as $iso=>$label)
                    <span class="badge" style="{{ in_array($iso, $schedule['weekdays']) ? 'background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; font-weight: 700;' : 'opacity: 0.5;' }}">
                        {{ $label }}
                    </span>
                @endforeach
            </div>

            <p style="margin: 0.85rem 0 0.4rem 0; font-weight: 650; font-size: 0.85rem;">Khung giờ hoạt động (Windows):</p>
            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                @foreach($schedule['windows'] as $window)
                    <span class="badge" style="font-family: var(--font-mono); font-weight: 700;">
                        {{ $window['start'] }} – {{ $window['end'] }}
                    </span>
                @endforeach
            </div>
        </div>

        <p class="muted" style="font-size: 0.8rem; margin: 0 0 1rem 0;">
            Khung giờ làm việc này do hệ thống cấp nhằm tự động xếp lịch và chống trùng lặp.
        </p>

        <a href="{{ route('profile.edit') }}" class="button secondary" style="font-size: 0.85rem;">
            Đổi múi giờ cá nhân trong Hồ sơ ›
        </a>
    </section>
</div>
</x-layout>

# Phần 5 — Calendar/Timeline, settings và thông báo

Ngày 17/10/2026. Chủ dự án đã duyệt phần 1–4 và yêu cầu phần 5. Backend/Blade phần 5 đã triển khai, xác minh local và **dừng chờ duyệt**. Chưa sang phần 6. Không thêm test class/mocks, migration, key hay tích hợp ngoài; không reset DB/APP_KEY. Dữ liệu review phần 1–4 được giữ.

## 1. Flow tạo/sửa lịch

`routes/calendar.php` nằm trong auth + active.user + workspace.member của web.php. GET create → `CalendarEventController::create()` → `context()` lấy project được phép tạo, WORK đang mở được giao cho người dùng, thành viên đang hoạt động → `calendar/form.blade.php`. Không dựng JSON API.

POST store / PATCH update → `CalendarEventRequest::rules()` → controller → `CalendarService::save()`:

1. Transaction, khóa User chủ lịch và Workspace; cập nhật còn khóa event, kiểm tra policy và version. Khóa User nhằm tuần tự hóa booking cùng người ở mọi workspace; có retry transaction 3 lần. Chưa xác minh tải đồng thời trên MySQL.
2. Tải project đúng workspace, kiểm tra quyền calendar.create/update trong scope tương ứng, project chưa archive/completed/cancelled. Scope project dùng role project riêng; active membership và workspace.view vẫn bắt buộc. Quyền workspace calendar không thay cho quyền project.
3. Server gán user_id=actor, workspace_id từ route, source=manual; không nhận plan/integration/external_id. Sau tạo không đổi project_id/task_id/event_type. Form sửa dùng hidden giá trị gốc và disable 3 select.
4. TASK_BLOCK chỉ nhận WORK lá, chưa đóng, được giao cho actor và actor có task.update trong đúng scope; không all-day. GROUP/MILESTONE không được lập khung làm việc. Người tạo Task/Owner không tự đặt lịch thay assignee. Event khác không nhận task_id. Lịch không thay trạng thái Task, estimate hay time_entries.
5. `localTime()` parse theo timezone nhập, so khớp định dạng để chặn giờ không tồn tại. datetime-local không chứa UTC offset nên từ chối phút bị lặp khi DST; có thể chọn giờ khác hoặc nhập theo UTC. Lưu UTC, hiển thị theo users.timezone. Đổi múi giờ hồ sơ không sửa các instant đã lưu.
6. Khoảng `[starts_at, ends_at)` phải tăng, tối đa 93 ngày. All-day nhập Y-m-d với ngày cuối loại trừ; TASK_BLOCK nhập giờ cụ thể. MEETING/BUSY/TASK_BLOCK luôn busy; NOTE tùy is_busy; EXTRA_AVAILABLE luôn không busy.
7. Busy planned/completed không được giao nhau với busy không cancelled của **cùng chủ lịch trên tất cả workspace**. Điều kiện old.start < new.end và old.end > new.start; hai lịch liền nhau được phép. Lỗi chỉ báo trùng giờ, không lộ lịch riêng ở scope khác. NOTE không busy/EXTRA_AVAILABLE không giữ chỗ. Cancelled giải phóng khung giờ; khôi phục planned phải kiểm tra xung đột lại.
8. participants là danh sách UUID distinct tối đa 20, kiểm tra active và quyền trong scope. Workspace event: workspace.view + calendar.view. Project event: task.view trong project. Đây là danh sách tham khảo, không tự đặt lịch cho người tham gia, không kiểm tra lịch của họ và không cấp quyền xem bằng participants.
9. Eloquent observer kiểm tra FK/quan hệ V3. Event TASK_BLOCK thay đổi ghi `TaskHistory::record('calendar.saved')` trong cùng transaction; no-op không thêm history. Không phát thông báo lịch giả.

Sửa TASK_BLOCK thông thường vẫn yêu cầu current assignee + task.update. Chủ event còn quyền xem được hủy khung lịch cũ sau khi task đã đóng/được giao lại; project vẫn phải writable. Không sửa event đã gắn time_entries. Event từ plan/integration hoặc source khác manual không sửa/xóa qua flow này.

`CalendarService::version()` băm raw attributes. Form update/delete gửi version; dữ liệu thay đổi từ khi mở form sẽ báo tải lại. Đây là guard stale theo nội dung; không cam kết chặn kiểu ABA (thay đổi rồi trở lại đúng giá trị trong cùng giây).

## 2. Xem lịch, riêng tư và Timeline

`CalendarEventController::index()` → `CalendarViewService::range()` → `rows()` → Blade.

- view=month/week/list; date=Y-m-d, hoặc from/to kết thúc loại trừ, tối đa 93 ngày; mặc định tuần hiện tại theo timezone người xem. Month lấy đúng tháng, week bắt đầu thứ Hai, list mặc định 30 ngày. include_cancelled=1 để hiện cancelled.
- Query sự kiện giao với phạm vi ngày, đúng workspace/project có quyền. Tối đa 1.000 event ứng viên; vượt giới hạn báo thu hẹp ngày, không âm thầm cắt kết quả. Không tải toàn bộ event hệ thống.
- Scope workspace chỉ thấy workspace event khi có calendar.view workspace; vẫn có thể xem project event nếu role project có calendar.view độc lập. Form tạo tương tự chấp nhận quyền từ ít nhất một project.
- `CalendarEventPolicy::view()` kiểm tra membership/quyền hiện tại, chủ event còn active workspace.view, task.view nếu có task, và visibility. Workspace event có thể private/workspace; project event chỉ private/project, không phát nội dung project ra toàn workspace.
- Riêng tư của người khác: chỉ busy được trả projection tên “Bận (riêng tư)”, owner, thời điểm/all_day; không id, title gốc, description, task, participants hay link chi tiết. NOTE riêng không busy và cancelled riêng được ẩn. Direct URL riêng trả 403 cả với Owner workspace. Người tham gia không tự được mở nội dung riêng.
- Chỉ chủ event + quyền calendar.update/delete tương ứng mới sửa/xóa; role Owner không được sửa sự kiện của người khác. Member mặc định có update nhưng không delete nên dùng hủy lịch.
- `CalendarEventController::bound()` xác minh event.workspace_id đúng route; event được bind độc lập rồi kiểm tra scope tường minh, không dựa vào relation tên event. Scope sai trả 404.
- `TimelineController::index()` kiểm tra view Project, dùng cùng range/rows đã phân quyền, chỉ lấy TASK_BLOCK có nội dung được phép xem; group theo task và tính offset_percent/width_percent theo giao với range để Blade dựng thanh lịch. Có link event và task. Không lấy estimate/deadline làm khung lịch giả và không lộ khung private qua task ID.
- config/work_schedule.php vẫn là nguồn weekdays/windows/timezone/slot_minutes mặc định; settings hiển thị read-only, calendar/timeline context nhận schedule. Manual event có thể ngoài giờ mặc định; engine tính capacity/AI chưa làm trong phần này. EXTRA_AVAILABLE chỉ biểu diễn ngoại lệ rảnh, không xóa busy.

## 3. Xóa lịch

DELETE → `CalendarEventController::destroy()` → validate version → `CalendarService::delete()` → lock + policy + version + không time_entries → history calendar.deleted nếu TASK_BLOCK → xóa event trong cùng transaction. Không xóa task/history liên quan. Quyền delete khác quyền cancel/update.

## 4. Settings

GET settings → `SettingController::edit()` → `SettingService::values()` hợp nhất default có kiểm tra whitelist → settings/edit. PATCH → `SettingRequest` → `SettingService::save()` khóa User và updateOrCreate một UserSetting theo user_id trong transaction.

Chỉ lưu language vi/en, theme system/light/dark, notifications.in_app boolean; email/telegram luôn false. Nested notifications chỉ cho các key đã biết. Từ chối quyền/token/user_id/working_hours và bật kênh chưa cấu hình; field khác không được validated/save. Không lưu giờ mặc định, timezone, token hay log vào JSON này. timezone vẫn qua ProfileRequest/ProfileController ở phần 2.

View composer components.layout nạp preferences và unreadNotifications. Theme đã có CSS cơ bản, html lang lấy ngôn ngữ ưu tiên; giao diện nhãn hiện vẫn tiếng Việt, chưa phải bộ dịch đầy đủ tiếng Anh. FE tiếp tục hoàn thiện nhãn/style nhưng không đổi schema settings.

## 5. Thông báo thật từ Task

Các TaskService/TaskWorkflowService đã ghi HISTORY trong transaction. `TaskHistory::record()` tạo TaskActivity rồi gọi `NotificationService::taskEvent()` cùng transaction, không thêm observer phát lại.

| Sự kiện thật | Người nhận |
|---|---|
| task.created có assignee | assignee |
| task.updated đổi assignee | assignee mới |
| task.status_changed tới REVIEW | reviewer |
| task.status_changed tới trạng thái khác | assignee và người tạo |
| task.comment_added | assignee, reviewer, người tạo |

Loại trùng recipient, bỏ actor, bỏ user inactive/mất task.view/tắt in_app. Không gửi mọi sửa metadata, edit/delete comment hay group sync thành thông báo. dedupe_key=`activity:{history UUID}:{recipient UUID}` + unique DB + firstOrCreate. Mỗi row là một người nhận, body mã + tiêu đề task. `deliveries` chỉ có channel=in_app/status=sent/attempts=1/sent_at/last_error, biểu thị đã lưu inbox thành công. Không gọi mail/Telegram/queue; không gắn trạng thái sent cho kênh ngoài.

Inbox GET → `NotificationController::index()` → `NotificationService::visible()` lọc recipient và quyền workspace/project/task hiện tại bằng query → paginate20 (filter all/unread). Composer dùng **cùng visible query** để đếm badge, tránh lộ unread khi mất quyền.

PATCH read → `NotificationService::read(user,id,unread)` → visible query + khóa row → chỉ update read_at. Người khác hoặc đã mất quyền trả404. unread=1 đặt null; đánh dấu read lặp lại giữ read_at cũ. POST read-all chỉ cập nhật visible unread của chính người dùng. Không đổi deliveries khi đọc; hiện không có retry external delivery. Khi có dispatcher nhiều kênh ở phần sau, phải khóa row và merge deliveries trong transaction.

## 6. Minh chứng và giới hạn

Xem verification/stage05/README.md: form HTTP với session/CSRF, SQL read-only đối chiếu DB thật, browser tạo meeting, Timeline hiển thị slot/link, screenshot. Có báo cáo các điểm dừng/lỗi ban đầu và cách sửa, không xóa log thất bại để báo tất cả pass. Script checkpoint không phải test class và không dùng làm endpoint ứng dụng.

Chạy local SQLite/PHP 8.2.12; đã kiểm tra logic datetime/DST, settings/inbox và quyền qua HTTP. Chưa kiểm tra MySQL/MariaDB, tải đồng thời, thư viện lịch kéo thả, SMTP/Telegram, lịch chung đặt chỗ nhiều người, notification deadline scheduler hoặc plan pending thật. Plan/AI/report/queue thuộc các phần sau và chưa được triển khai. Dừng chờ chủ dự án duyệt phần 5.

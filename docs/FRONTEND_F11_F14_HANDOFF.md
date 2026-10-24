# Bàn giao FE bổ sung F11–F14 — 23/10/2026

Laravel MVC/Blade, form submit + redirect/session errors. Không tạo REST API, SPA hoặc logic quyết định quyền ở FE. Xem [báo cáo/flow](FULL_FUNCTIONAL_COMPLETION_2026-10-23.md). Backend, Blade dùng được trên review35bảng; migration chính vẫn đang chờ xác nhận riêng.

| Trang | Controller/service | Blade | Contract |
|---|---|---|---|
| /work-hours GET/PUT | WorkHoursController → WorkHoursService | settings/work-hours | version, timezone, weekdays[], windows[n][start/end], slot_minutes |
| /workspaces/{workspace}/team-calendar GET/POST | TeamCalendarController → TeamCalendarService | calendar/team | project_id?, recipient_id, task_id?, title, description?, event_type, starts_at/ends_at local, timezone, visibility |
| /team-calendar/{proposal}/{accept\|decline\|cancel} POST trong workspace | respond → respond | calendar/team | revision; focus UUID trên list dùng bởi notification |
| /workspaces/{workspace}/collaboration/tasks/{task}/watchers POST | TaskCollaborationController → watch | tasks/_collaboration | user_id; CSRF |
| cùng route /{user} DELETE | destroy → watch(remove=true) | tasks/_collaboration | _method=DELETE; CSRF |
| task comment hiện hữu POST | TaskWorkflowService::comment | tasks/workflow | content và mentions[]; tối đa20 |
| /workspaces/{workspace}/task-templates GET/POST | TaskTemplateController → create | templates/index | project_id?, name, task_ids[], frequency, interval, starts_on, local_time, timezone, ends_on?, deadline_offset_days? |
| /task-templates/{template} GET/PUT trong workspace | show/update | templates/show, templates/_settings | revision + name/cadence; không gửi/sửa definition |
| /{template}/instantiate POST | instantiate | templates/show | request_key UUID + revision; giữ nguyên key khi submit lại cùng ý định |
| /{template}/{pause\|resume\|archive} POST | state | templates/show | revision; không hiển thị resume khi đang active |

Các đường dẫn ngắn trong bảng nằm dưới /workspaces/{workspace}. Tên route nguồn đầy đủ ở routes/full_features.php, prefix workspaces.; giờ làm work-hours.edit/update. Mọi form giữ @csrf, method spoofing, revision/version, lỗi theo field và old(). Không bỏ các hidden input để làm đẹp. Tên field chính xác đối chiếu Blade/controller trước khi đổi markup.

Chỉ cần phát triển bố cục, khoảng cách, màu, icon và copy; giữ data-form/selector hook và hợp đồng POST hiện tại. TaskController đã cung cấp collaborationReady, collaborationPeople, watchers cho view; không truy vấn DB trong Blade. Giao diện schema-upgrade trả 503 khi bảng chưa đủ; không giấu thành công hoặc tạo dữ liệu giả.

Luồng UI: tạo lời mời không chiếm lịch; người nhận bấm nhận mới thấy lịch thật. Xung đột/revision lỗi phải hiển thị rõ và cho tải lại. Mẫu chỉ lưu cấu trúc; nút tạo sinh bộ task mới và lịch sử có link từng task. Không cung cấp nút sửa task_map/definition hoặc xóa run. Lịch làm tùy chỉnh giải thích fallback và việc snapshot cũ được giữ. Watcher không có quyền assign/review thêm; primary assignee duy nhất.

Quyền do service/Policy kiểm ở mọi request. List/count không dùng dữ liệu ngoài scope; không đưa danh sách người không hợp lệ vào JS. Calendar visibility private, người rời nhóm, archive project và stale forms phải giữ phản hồi backend. Không tự suy ra quyền chỉ từ tên Owner/Member.

Minh chứng UI: verification/full-function-2026-10-23/full-template-desktop.png và full-hours-mobile.png. Cần kiểm lại trang dài/mobile sau thay CSS, các thông báo validation, keyboard/focus và role chỉ đọc. Không sửa code backend để bỏ guard khi thiết kế FE.

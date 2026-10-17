# Minh chứng phần 5 — flow HTTP/browser/SQLite thật

17/10/2026. Không TestCase, PHPUnit, mock, DB reset, sửa đồng hồ hay gọi service ghi DB để thay thế request thật. HTTP script đọc form/CSRF/session rồi submit route ứng dụng; SQLite chỉ mở mode=ro để đối chiếu. Browser dùng cùng app trên 127.0.0.1:8123. Tất cả dữ liệu review trước đó giữ nguyên.

## Cách đọc các báo cáo

Đây là các lượt làm thực tế có dừng, sửa và tiếp tục từ checkpoint. Không cộng file before-* trùng bản để tăng số ca và không gọi journal có lỗi ban đầu là “tất cả pass”.

| Journal | Phạm vi / kết quả |
|---|---|
| before-blade-directive-fix.json | Tạo workspace/project đã lưu; redirect project gặp500 do hai directive Blade dính nhau. Đã thêm khoảng trắng/ngắt dòng, sau đó project render200 ở các journal tiếp theo. Workspace/project lượt đầu giữ lại. |
| http-flow.json, before-helper-field-fix.json | Login, tạo workspace/project/member/task, assignment notification, inbox/read ownership, settings/default/preference/injection và metadata không gửi trùng đã qua. Dừng ở script gửi participants thay vì participants[] của form thật, không tạo event. |
| calendar-flow.json, before-timeline-task-link.json | Sau sửa tên field trong helper: tạo BUSY/NOTE/EXTRA_AVAILABLE/MEETING/TASK_BLOCK, UTC, overlap/adjacent, FK/scope/participants/input, DST, all-day/overnight/privacy đã qua. Dừng ở kiểm tra Timeline có task link; page200 nhưng chưa render task UUID/link. Bổ sung link thực vào row. |
| timeline-inbox-flow.json | 50 quan sát, 20 đối chiếu verified=true; Timeline/link, week/month, stale form, cancel/reuse, delete own, REVIEW/DONE notices, no-op status/dedupe, thu hồi/khôi phục project và mark-all đã qua; có complete marker. |
| boundaries.json, before-boundary-helper-variable-fix.json | Thêm task/slot thật, conflict khi reschedule và reassignment notice đã qua. Helper dùng lại biến before (count thay vì event row) làm assertion tiếp theo sai dù DB không đổi. Giữ nguyên journal này. |
| boundaries-final.json | 35 quan sát, 13 đối chiếu verified=true: xác minh lại old assignee không sửa được UTC; khôi phục assignment, HTML escaping, đổi/khôi phục timezone thật, role workspace chỉ workspace.view vẫn dùng được quyền calendar project riêng, private task slot không lộ Timeline, conflict xuyên workspace, khóa event type/task, max participants, all-day task bị chặn, project archive403/restore, browser row và FK. Có complete marker. |
| final-readback.json | 14 quan sát, 9 đối chiếu verified=true; browser create/update meeting và mark-read thật, badge còn1 đúng DB, Timeline/bar/link, hidden scope contract và private projection, memberships/timezone được phục hồi, FK. Có complete marker. |
| schema-scope-final.json | Đối chiếu migration/PRAGMA: workspaces không có archived_at, chỉ loại tham chiếu null sai schema mới thêm; projectWritable và policy thật vẫn giữ. Archive project qua form → edit calendar403 → restore → edit200. Có complete marker. |
| technical-checks.txt, routes.json | 153 PHP source, 108 compiled Blade PHP, 101 route; lint, Pint formatting, Composer validate, view/route/config cache, git diff check và FK đã qua. Đây là kiểm tra kỹ thuật, không thay minh chứng nghiệp vụ. |

Trong quá trình tiếp tục boundary helper còn sửa tên data-form profile đúng là “profile” và tránh biến source bị prefix exec ghi đè. Các lỗi helper không được bỏ qua để làm xanh báo cáo; sau sửa đều submit route thật, đọc lại DB và có boundaries-final/final-readback. Resume scripts không phải endpoint, không được đưa vào routing hoặc nạp trong web app.

## Flow browser đã thực hiện

1. Owner mở Settings, xác nhận các trường và khung giờ config.
2. Mở Calendar create, nhập MEETING 07/12/2026 15:00–16:00 Asia/Ho_Chi_Minh, visibility workspace rồi bấm Lưu. Trang show xác nhận thành công. DB lưu 08:00–09:00UTC, is_busy1 dù người dùng không tick.
3. Mở edit: event_type/project_id/task_id disabled với hidden gốc; sửa description và submit thật. DB/HTML giữ type/phạm vi/instant.
4. Bấm Thông báo2 → bấm một “Đánh dấu đã đọc” → badge thành1; SQL xác nhận một trong hai notice của actor có read_at, deliveries không đổi.
5. Mở Timeline project khoảng07–14/12, thấy P5 Active Timeline Slot13:00–14:00, thanh tỷ lệ, link event/task, không có private task slot. Trang được giữ để chủ dự án duyệt.

Ảnh: event-browser.png (tạo), event-updated-browser.png (sửa), inbox-browser.png (read/badge), timeline-browser.png và timeline-final.png. Shell Blade có style cơ bản; FE tiếp tục thiết kế theo FRONTEND_HANDOFF.md.

## Dữ liệu để duyệt

- Owner hiện tại: demo1@example.test / Demo@123456 (local demo đã có).
- Workspace: 01a1492c-68e5-729b-8df8-6e4900a93d04.
- Project: 01a1492c-6ce4-737a-9cb7-39e9a48b30fb.
- Task flow review hoàn tất: 01a1492c-916f-70e4-b155-1b2a1f0f86b4 (DONE); lịch cũ cancelled không sửa status/actual.
- Task đang mở cho Timeline: 01a14930-55b3-7297-a943-9ba56009398a; slot planned 01a14930-59ec-7355-ace6-a6c4f669b372.
- Meeting tạo/sửa bằng browser: 01a1492e-84fd-71d0-8fa8-e951e14238af.
- Worker/reviewer review là tài khoản mới của walkthrough, tách dữ liệu trước. Cookie/state có token nằm storage/app/private/stage05-review (Git ignored), không đưa vào docs/Git. Email demo và ID có trong state riêng.
- Mở `/workspaces/{workspace}/calendar?view=list&from=2026-12-07&to=2026-12-14` hoặc `/workspaces/{workspace}/projects/{project}/timeline?view=list&from=2026-12-07&to=2026-12-14`. Các ngày tháng12 là fixture lịch thủ công để kiểm tra, không phải thay đồng hồ hệ thống.

## Chạy lại và giới hạn

http-walkthrough.py là procedural flow tạo workspace/users/dữ liệu mới trên server đang chạy. Các resume-calendar/resume-timeline/boundary/resume-boundaries/final-readback/scope-readback dựa checkpoint cụ thể; đọc nội dung và trạng thái hiện tại trước, không chạy lại tùy tiện trên task đã DONE/event đã cancelled. Không dùng migrate:fresh hoặc sửa SQL để ép báo cáo pass. technical-checks.py không gọi PHPUnit; Pint --test chỉ kiểm tra formatting.

Xác minh local SQLite/PHP8.2, dữ liệu thật qua middleware/controller/service. Chưa xác minh MySQL/production/tải đồng thời/1000-event load, SMTP/Telegram, scheduling nhiều người, full English translation, deadline scheduler/plan notifications thật. participants chỉ tham khảo, không đặt chỗ người khác; capacity/AI/queue/report ở phần sau. Không tuyên bố tất cả dự án đã hoàn thành. Dừng chờ duyệt phần5.

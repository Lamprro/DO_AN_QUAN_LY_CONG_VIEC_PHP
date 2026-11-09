# Gộp bảng và chuẩn hóa Model — 09/11/2026

Schema nguồn giảm từ **27 bảng nghiệp vụ + 8 hạ tầng = 35** xuống **25 + 8 = 33 bảng**. Hai migration mới **chưa chạy trên database chính (MySQL `quan_ly_cong_viec`)**. Chủ dự án cần xác nhận và sao lưu trước khi chạy `php artisan migrate`. Không dùng `migrate:fresh`.

## 1. Gộp `calendar_proposals` vào `calendar_events`

**Lý do.** `calendar_proposals` lặp lại 7 cột của `calendar_events`. Khi chấp thuận, hệ thống sao chép đề xuất sang một event mới, nên cùng một dữ liệu nằm ở hai nơi.

**Thiết kế mới.** Đề xuất là một dòng `calendar_events` có `proposed_by` khác null.
- `user_id` là người nhận, đồng thời là chủ lịch sau khi chấp thuận.
- Cột mới: `proposed_by` (FK users, restrict) và `responded_at`.
- Index mới: `(proposed_by, status)` và `(user_id, status)`.

```
proposed ──accept (người nhận)──▶ planned ──cancel (người nhận)──▶ cancelled
   │                                 └─ sửa tiếp như lịch thường (CalendarService)
   ├──decline (người nhận)──▶ declined
   └──cancel (người đề xuất)─▶ withdrawn
```

**Không để đề xuất lọt vào lịch.** `CalendarEvent` có global scope `on_calendar`, loại các trạng thái `proposed`, `declined`, `withdrawn` khỏi **mọi** truy vấn Eloquent. Các truy vấn được bảo vệ gồm: calendar, timeline, kiểm tra trùng lịch bận, local scheduler, PlanningContext AI, báo cáo, route binding `{event}`. Chỉ màn hình Team Calendar đọc sổ đề xuất, qua scope tường minh `CalendarEvent::proposals()`.

**Quy tắc giữ nguyên hoặc bổ sung**
- Khi còn ở trạng thái đề xuất, nội dung không được sửa. Đề xuất đã từ chối hoặc thu hồi không được mở lại. Dòng có `proposed_by` không được xóa (IntegrityObserver).
- Lịch nhận từ đề xuất chỉ được hủy, không được xóa (`CalendarEventPolicy::delete`). Trước đây có thể xóa event, còn đề xuất vẫn được giữ lại; nay chỉ còn một dòng nên phải giữ dòng đó.
- Form lịch thường chỉ nhận `planned`, `completed`, `cancelled`, và cấm `proposed_by`, `responded_at`.
- Chấp thuận gọi `CalendarService::acceptProposal`. Hàm này dùng chung với tạo lịch thường hai kiểm tra `taskFor()` (TASK_BLOCK hợp lệ) và `assertFree()` (không trùng lịch bận). Dòng đề xuất được chuyển trạng thái tại chỗ, không sao chép.
- Khóa lạc quan đổi từ `revision` (số nguyên) sang `version` (hash dòng, giống form lịch thường). Khóa thông báo: `calendar-proposal:{id}:{status}`.

**Chuyển dữ liệu (`2026_11_09_000001`)**

| Đề xuất cũ | Sau migration |
|---|---|
| pending | dòng mới cùng id, `proposed` |
| declined | dòng mới cùng id, `declined` |
| cancelled trước khi nhận | dòng mới cùng id, `withdrawn` |
| accepted / cancelled, event còn tồn tại | **event đó** được gán `proposed_by` và `responded_at`; giữ id event nên `time_entries` không mất liên kết; `dedupe_key` của thông báo trỏ sang id event |
| accepted, event đã bị xóa | dòng mới cùng id, `cancelled` (giữ lại làm lịch sử) |

`down()` tạo lại `calendar_proposals` và chuyển dữ liệu ngược lại theo cùng bảng ánh xạ.

## 2. Gộp `user_settings` vào `users.settings`

Đây là quan hệ 1–1 và bảng chỉ có một cột JSON. Migration `2026_11_09_000002` chép toàn bộ rồi xóa bảng; `down()` khôi phục lại. Mọi thao tác đọc và ghi đi qua `SettingService::stored()` và `SettingService::store()`. `TimeTrackingService` (giờ làm) dùng chung service này. `RegistrationService`, seeder và factory ghi thẳng vào `users.settings`.

## 3. Chuẩn hóa các model thêm từ 18/10 và 23/10

Chuẩn áp dụng cho mọi model:
- `$fillable` và `$hidden` khai báo rõ.
- `$attributes` khớp giá trị mặc định trong database.
- Timestamp và ngày giờ ép kiểu `immutable_*`.
- Cột trạng thái ép kiểu enum.
- Mỗi khóa ngoại có một quan hệ.
- Có `scopeForWorkspace` khi model có `workspace_id`.
- Được đăng ký vào `config/domain_schema.php` để IntegrityObserver kiểm tra.

| Model | Thay đổi |
|---|---|
| TaskTemplate | enum `TaskTemplateStatus` (active/paused/archived), `TaskTemplateFrequency` (daily/weekly/monthly); giá trị mặc định; `scopeForWorkspace` |
| TaskTemplateRun | enum `TaskTemplateRunStatus` (succeeded/failed) |
| TaskReminder | enum `TaskReminderKind` (due/review), `TaskReminderStatus` (queued/sent/skipped/failed); quan hệ `workspace`, `user`; giá trị mặc định |
| SavedTaskFilter | quan hệ `user`, `workspace`, `project`; `scopeForWorkspace` |
| TimeEntryAdjustment | quan hệ `timeEntry`, `actor` |
| TaskWatcher | ép kiểu timestamp |
| Task / TimeEntry | thêm `reminders()` / `adjustments()` |

`TaskReminder`, `SavedTaskFilter` và `TimeEntryAdjustment` được thêm vào danh sách model của IntegrityObserver, nên các tham chiếu chéo workspace được kiểm tra giống các model cũ. Service và view của template và reminder đã đổi từ so sánh chuỗi sang so sánh enum. `UiLabels` có thêm nhãn tiếng Việt cho các giá trị mới.

## 4. Kiểm chứng

Thực hiện trên bản sao `database/jira_board_review_20261101.sqlite` trong thư mục tạm; database gốc và MySQL không bị đụng tới. Bằng chứng và script nằm trong [verification/model-consolidation-2026-11-09](verification/model-consolidation-2026-11-09/).

1. **Code cũ (HEAD, chạy qua git worktree)** tạo 6 đề xuất thật qua `TeamCalendarService` cũ: chờ, từ chối, thu hồi, chấp thuận TASK_BLOCK, chấp thuận rồi hủy, chấp thuận rồi xóa event. Đồng thời lưu giờ làm riêng cho demo3. Xem `log_01_old_flow.txt`.
2. **Migrate bằng code mới.** Đối chiếu từng dòng theo bảng ánh xạ, thông báo được trỏ lại id đúng, settings 5/5 dòng. Xem `log_02_migrate.txt`.
3. **Luồng service mới: 45/45 PASS.** Bao gồm:
   - Global scope ẩn đề xuất khỏi calendar và timeline.
   - Đề xuất đang chờ không chiếm chỗ trong lịch, nhưng khi chấp thuận vẫn bị chặn nếu trùng lịch bận.
   - Đủ các thao tác chấp thuận, từ chối, thu hồi, hủy sau khi nhận, sai version, xử lý lại.
   - Các ràng buộc toàn vẹn dữ liệu.
   - Settings và giờ làm, đăng ký tài khoản mới.
   - Vòng đời template (due, update, pause, resume, instantiate, archive) và reminder (scan, deliver).

   Xem `log_03_new_flow.txt`.
4. **HTTP thật (cookie + CSRF): 30/30 PASS.**
   - Mở các trang: team calendar, calendar, timeline, settings, work-hours, reminders, notifications, task-templates, operations.
   - Chấp thuận bằng form thật; gửi lại cùng form thì bị chặn.
   - Không còn nút xóa trên lịch nhận từ đề xuất.

   Xem `log_04_http.txt`.
5. Trên một bản sao khác: `migrate:rollback --step=2` rồi `migrate` lại. 11/11 đề xuất và 6/6 settings khớp sau vòng khứ hồi.

**Giới hạn.**
- Các tài khoản demo trong DB review chưa xác minh email. Riêng trên bản sao, `email_verified_at` được gán để đăng nhập được; đây là dữ liệu sẵn có, không liên quan tới thay đổi lần này.
- Chưa chạy trên MySQL/MariaDB. Migration chỉ dùng Schema Builder và query builder, không dùng SQL riêng của từng engine, nhưng vẫn cần chạy thử trên bản sao MySQL trước khi chạy trên DB chính.
- `docs/design/schema-current.json` chưa được sinh lại; ERD_CURRENT.mmd đã sửa tay.

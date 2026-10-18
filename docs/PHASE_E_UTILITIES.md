# Đợt E — F04–F10, 18/10/2026

Đã triển khai backend, form Blade, database và flow thật. Giữ Laravel MVC/session/CSRF; không thêm API, SPA hay lớp test. Xem [bàn giao FE](FRONTEND_HANDOFF.md) và [minh chứng](verification/phase-e/README.md). Môi trường nghiệm thu là PHP/XAMPP, SQLite local; không coi đây là kiểm tra tải hoặc nghiệm thu MySQL/production.

## Chức năng và flow qua hàm

| Mục | Flow xử lý | Kết quả / giới hạn |
|---|---|---|
| F04 Danh sách nhanh | TaskController::index → TaskFilters::clean/applyPreset → query scope → filter/sort/paginate → tasks/index | Việc của tôi, chờ tôi duyệt, blocked, WORK quá hạn; lọc SQL trước phân trang. Mine theo assignee, không gồm GROUP; có thể gồm việc đóng nếu chưa chọn status. Review theo quyền hiện tại, reviewer hoặc Owner theo chính sách duyệt cũ. Blocked là open blocker, không đồng nghĩa mọi dependency chưa đủ. |
| F05 Lưu bộ lọc | TaskUtilityController::store → TaskUtilityScope::check → TaskUtilityService::saveFilter → saved_task_filters; open → bound/clean → redirect list; destroy → bound → delete | Riêng user/workspace/project, tối đa20/tên không trùng. Chỉ lưu whitelist, không lưu URL/raw request/credential. Mở lại kiểm scope hiện tại; bộ lọc không trao quyền. Xóa chỉ cấu hình bộ lọc, không xóa task. |
| F06 Hàng loạt | TaskUtilityController::bulk → TaskUtilityService::bulk → khóa workspace/task → policy+EditRevision tất cả → TaskService::save/archive từng dòng → commit chung | Tối đa50 cùng scope, đổi priority/giao việc/archive. Một lỗi rollback cả task/history/notification. Phân công vẫn cần task.assign. Không duyệt hoặc DONE hàng loạt; GROUP có con active không archive tự động. |
| F07 Nhắc việc | tasks:remind hoặc UtilityObservationController::scan → TaskReminderService::scan/eligible/source → ledger+database job → SendTaskReminder::handle → deliver → recheck → Notification | In-app thật, quá hạn hoặc deadline trong24h cho assignee; REVIEW chờ≥60phút cho reviewer (Owner fallback khi chưa có reviewer). Lặp cùng nguồn/loại/user/ngày/timezone không gửi trùng. Quyền, preference, status, assignee/reviewer, deadline/lượt REVIEW, timezone/ngày được kiểm lại khi giao. Sai nguồn đánh skipped, không báo sent. |
| F08 Hạn và điều kiện hiện tại | TaskService::save kiểm deadline_reason khi đổi hạn cũ → TaskHistory::record task.deadline_changed; TaskController::show → DeadlineInsight::build → tasks/_deadline | Nhãn WORK quá hạn, blocker mở, predecessor/FS-lag, REVIEW, chưa giao; tối đa20 lần đổi hạn mới có actor/reason/before/after. Không bịa lịch sử cũ hoặc khẳng định điều kiện hiện tại là nguyên nhân trễ đã được chứng minh. |
| F09 Quan sát vận hành | UtilityObservationController::operations → QueueObservation::data → quyền workspace.view_operations → own runs/current source → metadata jobs/failed_jobs + own reminders → utilities/operations | Owner mặc định, role khác cần quyền được chủ hệ thống chủ động cấp. Không sửa role/membership hiện có. Chỉ run chính actor, tối đa100; calls_count và link run. Chỉ metadata job liên quan, không prompt/context/exception/payload. Pending đọc tối đa1000+1, failed tối đa1000; không là toàn queue, không heartbeat, không suy worker sống từ số0. Retry/cancel dùng flow AiRun hiện có. |
| F10 Sửa giờ có lịch sử | TimeAdjustmentController::edit → allowed/version → timers/adjust; update → validate → TimeAdjustmentService::save → khóa user/workspace/entry → quyền/version/timezone/localTime/overlap → update+TimeEntryAdjustment+TaskHistory | Chỉ chính chủ phiên đã kết thúc và còn quyền nguồn, không manager sửa người khác. Bắt buộc reason; không tương lai/trùng phiên khác kể cả workspace khác và phiên đang chạy. Hash gồm giá trị và số audit, ngăn lưu từ hai tab cũ. Snapshot cũ giữ nguyên; dashboard/snapshot mới tính giờ đã chỉnh. Không tạo time entry thủ công hay quy trình xin duyệt mới. |

### Những điểm cần giữ khi phát triển

Saved filter là cấu hình riêng, không snapshot dữ liệu/quyền. Routes GET/DELETE chứa `{saved}` chủ động bỏ scoped relationship binding vì SavedTaskFilter không phải quan hệ `Project::saveds`; controller kiểm exact user/workspace/project. Không bỏ các kiểm tra này khi đổi route.

Bulk gửi revision từng task đã nhìn thấy, không tải revision mới rồi tự retry. Tất cả kiểm tra và ghi dùng transaction chung, gọi lại service chính để không tạo nhánh nghiệp vụ riêng. Không chọn ngầm các trang khác hoặc toàn bộ kết quả tìm kiếm.

Reminder ledger và job dùng cùng database connection với dữ liệu nghiệp vụ; cấu hình connection khác bị từ chối rõ. Notification và sent ledger lưu nguyên tử. Trạng thái queued nghĩa đang chờ; skipped/failed khác sent. Retry job không tự tạo notification thứ hai nhờ unique dedupe. Không gửi SMTP/Telegram hoặc bật AI để kiểm thử nhắc việc.

TimeEntryAdjustment không có endpoint sửa/xóa, Eloquent chặn update/delete; lưu before/after UTC, actor và reason. Đây là bảo vệ ở ứng dụng, không phải bằng chứng chống quản trị viên dùng SQL trực tiếp. Version/timezone cũ phải được giữ khi validation lỗi, `?fresh=1` là người dùng chủ động bỏ bản nhập. Dữ liệu nhập theo timezone actor, hiển thị lịch sử UTC rõ ràng.

## Database và cập nhật nguồn

Migration `2026_10_18_000021_add_task_utilities.php`: saved_task_filters, task_reminders, time_entry_adjustments; index task scope/assignee/reviewer/deadline và blocker. Migration `000022_add_time_overlap_index.php`: user/started_at/ended_at cho kiểm trùng phiên. Chỉ chạy migration bổ sung:

```powershell
php artisan migrate --force
php artisan optimize:clear
```

Giữ .env/APP_KEY/database cũ. Không migrate:fresh, reset hoặc seed lại database đang dùng. Query plan thật ở technical.json cho thấy planner chọn index; dữ liệu nhỏ không đủ kết luận tốc độ hoặc khả năng chịu tải.

## Chạy nhắc việc khi triển khai

Trong thư mục dự án, hai tiến trình độc lập:

```powershell
php artisan schedule:work
php artisan queue:work database --queue=work-management-reminders --sleep=1 --tries=3 --timeout=45
```

Windows có `scripts/work-reminders.ps1`, thêm `-Drain` để xử lý hết queue hiện có rồi thoát. AI worker riêng vẫn `scripts/work-ai.ps1`. Scheduler đã đăng ký quét hourly/withoutOverlapping55, nhưng chưa cài Windows Task Scheduler/service hay cron production. Phải cấu hình tiến trình giám sát khi đưa lên server; không tuyên bố tự chạy nền vĩnh viễn sau khi đóng terminal. Chạy từng đợt với phạm vi rõ:

```powershell
php artisan tasks:remind --workspace=<UUID> --user=<UUID>
```

`--review-after=0` chỉ dùng trong flow nghiệm thu để xử lý lượt REVIEW vừa tạo, không sửa clock/timestamp SQL; mặc định vận hành60phút. Scan web chỉ chính actor/workspace, giới hạn3lần/phút. Key/AI/integrations vẫn chưa bật; provider tắt trả failed thật.

## Kết quả xác minh và giới hạn

Đã chạy HTTP có cookie/CSRF/Blade và SQL đọc lại: nhiều trang, private filters, quyền và scope âm tính, bulk rollback và thành công, deadline có reason, dedupe/skip với worker thật, AI disabled thật, timer thật/start-stop, overlap/future/stale/timezone/foreign owner, snapshot cũ/mới. Browser còn lưu filter và chỉnh ghi chú phiên thật từ form, có ảnh và SQL audit. Không thêm lớp test.

Flow dùng fixture mới có tiền tố Eutil1792292478 trong workspace demo đã có; không xóa/reset dữ liệu cũ, không cấp role/quyền mới cho tài khoản hiện hữu. Không thao tác thu hồi membership hoặc đổi preference của người dùng cũ chỉ để tạo tình huống; guard có trong mã nhưng chưa nghiệm thu các tình huống đó bằng thay đổi quyền thật. Không đo tải lớn, SMTP/network AI, crash recovery hay cạnh tranh nhiều process trên MySQL. Flow hai tab stale đã kiểm tuần tự qua hai session, không phải phép đo race đa tiến trình.

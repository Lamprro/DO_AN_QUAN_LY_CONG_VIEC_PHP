# Xác minh trực tiếp phần 1 — 17/10/2026

Theo yêu cầu chủ dự án, không dùng các lớp test làm căn cứ nghiệm thu. Hai lớp FoundationTest/FoundationLifecycleTest vừa tạo đã được gỡ khỏi dự án. Các bằng chứng dưới đây được tạo bằng chính Artisan/Eloquent/SQL của ứng dụng và file SQLite có dữ liệu thật đã ghi, không mock DB hay chạy flow trong TestCase.

## Phạm vi và giới hạn

Phần 1 mới có schema/model/observer/seed, chưa có auth/controller nghiệp vụ. Vì vậy đây là xác minh flow dữ liệu, chưa phải kiểm thử luồng HTTP/MVC hoàn chỉnh. Thao tác trực tiếp đổi status/blocker hoặc tạo plan ở đây chỉ chứng minh lưu trữ và invariant; không chứng minh state machine, quyền hoặc duyệt AI đã hoàn thiện.

## Bằng chứng đã thực hiện

| Bước | Thao tác thật | Kết quả quan sát | File bằng chứng |
|---|---|---|---|
| 1 | Artisan tạo workspace/role/member/project/cây task, sửa task, tạo dependency/blocker/lịch/activity | Task FLOW-001 được lưu với title mới, IN_PROGRESS, estimate 45; blocker được lưu resolved | 01-create-flow.tinker.txt và 01-create-flow.log |
| 2 | Mở tiến trình Artisan khác, dùng SQL JOIN đọc lại task/project/workspace/user và Eloquent relationships | Đọc đúng dữ liệu đã commit; parent FLOW-GROUP có FLOW-001/FLOW-002; lịch trỏ FLOW-001 | 02-read-back.tinker.txt và 02-read-back.log |
| 3 | Đếm schema qua kết nối DB thật | 20 bảng nghiệp vụ, 61 FK; PRAGMA foreign_key_check không có dòng lỗi | 02-read-back.log |
| 4 | Ghi mã task trùng | SQLite báo UniqueConstraintViolationException trên workspace_id/code | 03-invalid-writes.log |
| 5 | Ghi estimate âm, cycle cha/con, cycle dependency, role khác workspace, range lịch sai | ValidationException từ observer thật; dữ liệu không bị ghi sai | 03-invalid-writes.tinker.txt và 03-invalid-writes.log |
| 6 | Sửa/xóa HISTORY và xóa COMMENT | HISTORY bị chặn; COMMENT có deleted_at; SQL đọc lại HISTORY giữ nguyên, estimate vẫn 45 | 03-invalid-writes.log |
| 7 | Lưu hai plan inactive và một active, sau đó ghi active thứ hai cùng group | Hai inactive tồn tại; active thứ hai bị DB unique active_group_key chặn | 04-database-constraints.log |
| 8 | Tạo timer đang chạy, tạo timer thứ hai cùng user, rồi kết thúc timer đầu | Timer thứ hai bị DB unique chặn; sau khi kết thúc running_user_key trở thành NULL | 04-database-constraints.log |
| 9 | Lưu credential giả ở connection disabled, đọc cột DB và serialization | Plaintext không có trong DB; toArray không chứa credential; giải mã fixture thành công | 04-database-constraints.log |
| 10 | Raw SQL chèn membership có workspace không tồn tại | SQLite trả FOREIGN KEY constraint failed; FK check sau đó vẫn không có lỗi | 04-database-constraints.log |
| 11 | Database riêng: migrate → seed → seed lần hai → rollback → migrate → seed | Mỗi lệnh hoàn tất; trước/sau seed đều 5 users, 40 tasks, 20 events; FK check rỗng | 05-migration-lifecycle.log |

Trong lần kiểm tra lifecycle đầu tiên, SQLite chặn DROP tasks do self-FK RESTRICT. Đã sửa down() của tasks/projects/plans để gỡ self-parent references trước khi DROP. Vòng lifecycle chạy trực tiếp sau sửa đã hoàn tất; không tắt FK toàn cục.

## Cách xem lại bằng dữ liệu hiện có

Database hoạt động là `database/work_management.sqlite`; giữ nguyên `database/database.sqlite` cũ. Có thêm workspace `Review Flow - Stage 01` phục vụ người duyệt, ngoài 3 workspace của seeder.

```powershell
php artisan tinker
```

Sau đó có thể nhập trực tiếp:

```php
$task = App\Models\Task::where('code', 'FLOW-001')->firstOrFail();
$task->only(['id', 'code', 'title', 'status', 'estimate_minutes']);
$task->project->name;
$task->parent->children()->pluck('code');
$task->calendarEvents()->get();
DB::select('PRAGMA foreign_key_check');
```

Các file .tinker.txt là chính câu lệnh đã chạy để tái hiện, không phải lớp test; chúng ghi dữ liệu nên đọc nội dung và chỉ chạy trên database review/demo. File 03 cần COMMENT còn tồn tại để thao tác soft-delete; sau một lần chạy lại cần chọn comment mới phù hợp. File 04 có chèn các plan để kiểm tra constraint, các plan này được ghi nhãn **Storage constraint review (not approval workflow)**, không coi là luồng duyệt plan thật.

File lifecycle là `database/stage01_lifecycle.sqlite` riêng. Lệnh vận hành tương ứng: đặt DB_DATABASE trong tiến trình terminal sang file kiểm tra riêng → migrate → db:seed → db:seed → migrate:rollback → migrate → db:seed. Không dùng migrate:rollback trên database hoạt động nếu cần giữ dữ liệu.

Logs gốc đuôi .log hiện bị .gitignore mặc định loại trừ; bản .txt kèm theo có cùng nội dung để có thể nộp/commit bằng chứng. Các token xuất hiện trong kiểm tra là fixture giả, connection giữ disabled; không có khóa tích hợp thật.

MySQL/MariaDB, giao diện và HTTP/controller nghiệp vụ chưa được kiểm tra ở phần 1. Các phần tiếp theo phải chạy luồng thật qua route/controller/service/DB đã triển khai và lưu lại request, lỗi, dữ liệu trước/sau; không dùng kết quả mô phỏng để công bố tính năng đã hoạt động.

## Phần 2

Phần 2 đã có HTTP MVC và Blade. Minh chứng riêng tại [stage02/README.md](stage02/README.md), không suy rộng các giới hạn của phần 1 sang trạng thái phần 2.

## Phần 3

Project/project member/CRUD Task đã có HTTP và Blade. Minh chứng tại [stage03/README.md](stage03/README.md).

## Phần 4

Minh chứng cây/status/review/dependency/blocker/activity/private file thật tại [stage04/README.md](stage04/README.md).


## Phần 5 Calendar/Timeline/settings/thông báo

Xem [minh chứng phần 5](stage05/README.md), bao gồm flow HTTP/browser/SQLite thật, các điểm dừng/lỗi đã sửa, giới hạn và tài khoản/dữ liệu review. Phần 1–4 đã được duyệt; phần 5 dừng chờ duyệt. Không thêm test class/mocks.


## Phần 6 Dashboard/report, queue và nền AI

Xem [minh chứng phần 6](stage06/README.md): HTTP/browser/SQLite + worker thật, snapshot cố định, CSV, nguồn actual qua model có giới hạn được ghi rõ và AI lỗi cấu hình minh bạch. Phần1–5 đã duyệt, phần6 chờ duyệt; không có provider AI thật/Plan phần7.


## Hoàn thiện phần7–10

Xem [minh chứng cuối](final/README.md) cho Plan/version/apply, scheduler local, timer HTTP thật, AI disabled queue, GitHub signed local payload, phân quyền và cài source riêng. DELIVERY_STATUS.md là trạng thái hiện tại thay các mục chờ duyệt lịch sử.

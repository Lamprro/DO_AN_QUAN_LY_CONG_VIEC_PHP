# Kiểm chứng tái cấu trúc kiến trúc — 06/11/2026

Mục tiêu: chứng minh việc sắp xếp lại các lớp (xem [ARCHITECTURE.md](../../ARCHITECTURE.md)) **không đổi hành vi**: cùng URL, cùng tên route, cùng kết quả phân quyền, cùng dữ liệu ghi xuống DB. Kiểm bằng HTTP thật trên app thật và MySQL thật, không TestCase, không mock, không dùng test suite mặc định.

## Môi trường

- Máy làm việc không cài PHP/MySQL; dùng Docker: PHP 8.3 CLI (+`pdo_mysql`, `bcmath`, `pcntl`) và MySQL 8.0.
- Database **review riêng** `wm_review` trong container `wm-mysql`, tạo bằng `migrate` + `db:seed` (DemoSeeder, FactorySeeder, RichDataSeeder). Không đụng database chính, không `migrate:fresh` trên dữ liệu thật.
- File cấu hình `.env.review` (đã nằm trong `.gitignore`), `AI_ENABLED=false`, `MAIL_MAILER=log`.
- Sau khi seed, dump DB thành snapshot; **mỗi lần chạy kịch bản đều khôi phục snapshot này** nên bản gốc và bản mới bắt đầu từ cùng một trạng thái.
- Bản gốc = `git archive HEAD` (commit `e38d8e7`), vendor + autoload riêng, chạy trên port 8001. Bản mới = working tree, port 8000. Cả hai dùng chung `wm_review`.

## Các bước và kết quả

| # | Kiểm tra | Kết quả |
|---|---|---|
| 1 | `php -l` toàn bộ `app/ routes/ bootstrap/ config/ database/` | Không lỗi cú pháp |
| 2 | PHPStan level 1 (`app/`, `routes/`, bỏ qua magic Eloquent) | `[OK] No errors` (file thử chứa class không tồn tại bị bắt đúng → công cụ hoạt động) |
| 3 | PHPStan level 2, so lỗi trước/sau theo nội dung thông điệp | 67 → 58; chỉ 1 thông điệp mới: `Query\Builder::whereHas()` trong `TaskPageData` — false positive do type hint `HasMany`, nhánh `preset=blocked` đã chạy thật ở bước 5 |
| 4 | `artisan route:list --json` trước/sau | 173 → 166. Bỏ đúng 7 route rác `workspaces.password.*`, `workspaces.verification.*` (do `account_security.php` bị require 2 lần). 2 route `*.comments.destroy` trỏ sang `TaskWorkflowController@deleteComment`. 164 route còn lại trùng khớp URI, tên, action, middleware |
| 5 | Crawl GET: 6 tài khoản (Owner, Manager, 2 Member, Viewer, người ngoài) × mọi route GET × 4 workspace (team, personal, AlphaTech, workspace của người khác), có biến thể `view=board`, `preset=*`, `project_id`, `panel=1`, `activity_type` | **1.548/1.548 request trùng khớp** status, redirect, title/h1, tập form action, `_method`, hash tập link, số nút, số `disabled`. Tổng thời gian 107,8 s → 63,5 s |
| 6 | Kịch bản ghi 109 bước (`scripts/mutate.py`): workspace, vai trò, hạ/khôi phục quyền thành viên, mời, project (tạo/sửa/stale/thành viên/lưu trữ/xoá), task (tạo/sửa/409 conflict/giao việc/trạng thái/duyệt/comment/mention/blocker/dependency/archive/bulk/bộ lọc/watcher/xoá), lịch (tạo/sửa/xoá + version), kế hoạch (tạo/đề xuất/duyệt/preview/xếp lịch), báo cáo, AI run (AI tắt), timer (start/stop/điều chỉnh), mẫu, GitHub, nhắc việc, task cá nhân, workspace mới, thông báo, hồ sơ, rời workspace, xoá workspace có dữ liệu — mỗi bước kèm vai trò không đủ quyền | **109/109 bước trùng khớp** (status, URL redirect, flash, lỗi validate từng trường, title trang lỗi). Dấu vân tay DB sau kịch bản (22 bảng, bỏ id/timestamp): **22/22 trùng khớp** |
| 7 | Lệnh Artisan + queue worker trên bản mới: `tasks:remind --review-after=0`, `tasks:generate-recurring`, `ai:recover-stale`, `queue:work` cho `work-management-ai` và `work-management-reminders` | 13 lời nhắc xếp hàng → 13 job `SendTaskReminder` DONE → 13 thông báo, `failed_jobs` = 0. 2 job `ProcessAiRun` kết thúc `failed` với thông điệp "AI đang tắt…" — đúng thiết kế khi chưa cấu hình AI |
| 8 | `storage/logs/laravel.log` của bản mới sau bước 5–6 | Chỉ có lỗi đã có từ trước (xem dưới) |

Phân bố status trong kịch bản ghi: 71 redirect thành công/lỗi validate, 33 bị chặn 403, 1×404 (mở bộ lọc của người khác), 1×409 (đối chiếu sửa task), 1×419 (người ngoài workspace), 2×200.

## Lần chạy không tính là đạt

`results/attempt-write-flows-timer-same-second.json`: lần chạy kịch bản bản mới đầu tiên lệch 2 bước timer so với bản gốc. Nguyên nhân: start và stop cùng một giây nên `ended_at == started_at` bị từ chối; ở lần chạy bản gốc đầu tiên cũng xảy ra đúng lỗi này. Đã thêm chờ 1,2 giây giữa start/stop (giống ghi chú ở `verification/ux25`) rồi **chạy lại cả bản gốc và bản mới**; kết quả bước 6 ở trên là của lần chạy lại đó.

## Lỗi có sẵn phát hiện thêm (không thuộc phạm vi đợt này, chưa sửa)

`GET /workspaces/{AlphaTech}/task-templates/{template}` trả 500 ở **cả bản gốc lẫn bản mới**: `Undefined array key "task_type"` trong `resources/views/templates/show.blade.php`. Mẫu do `RichDataSeeder` tạo có `definition.tasks[*]` thiếu khoá `task_type`. Cần sửa seeder hoặc cho view chịu được khoá thiếu.

## Giới hạn

- Không có trình duyệt thật/JavaScript: phần panel AJAX được kiểm qua HTML/HTTP; giao diện trực quan không đổi vì Blade chỉ đổi cách lấy cờ quyền.
- Upload/download tệp đính kèm và webhook GitHub ký HMAC chưa nằm trong kịch bản; code của hai luồng này chỉ đổi cách nhận request/validate, logic Service giữ nguyên.
- Chưa kiểm với Redis (`INVITATION_CACHE_STORE=redis` ở production); review dùng `file`.
- Chưa chạy trên database chính của chủ dự án.

## Tái hiện

```bash
# 1. MySQL + PHP image (Dockerfile: FROM php:8.3-cli + docker-php-ext-install pdo_mysql bcmath pcntl)
docker network create wm-review
docker run -d --name wm-mysql --network wm-review -e MYSQL_ROOT_PASSWORD=review -e MYSQL_DATABASE=wm_review mysql:8.0
# 2. composer install, tạo .env.review (DB_HOST=wm-mysql, DB_DATABASE=wm_review, AI_ENABLED=false), key:generate, migrate, db:seed
# 3. mysqldump wm_review > seed.sql ; chạy app: php artisan serve (APP_ENV=review)
# 4. Trước mỗi lần chạy: mysql wm_review < seed.sql ; php artisan cache:clear file
python3 -I scripts/crawl.py  http://localhost:8000 routes.json get.jsonl
python3 -I scripts/mutate.py http://localhost:8000 write-flows.json
python3 scripts/compare.py get-before.jsonl get-after.jsonl
```

Script đọc ID thật từ container `wm-mysql` và đăng nhập bằng tài khoản demo của `DemoSeeder`.

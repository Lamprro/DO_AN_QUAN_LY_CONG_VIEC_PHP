> Tài liệu phát triển tiếp: [Cải thiện UI/UX và chức năng](docs/UI_UX_FUNCTIONAL_IMPROVEMENTS.md), gồm backlog theo màn hình, ưu tiên và tiêu chí nghiệm thu.

Vòng P0 đã triển khai: [hiện trạng và flow](docs/P0_IMPLEMENTATION.md), [bàn giao FE mới](docs/FRONTEND_HANDOFF.md), [minh chứng kiểm tra trực tiếp](docs/verification/p0/README.md). Khi cập nhật chạy migration thêm revision; giữ DB/.env/APP_KEY hiện tại.

> Bản bàn giao đầy đủ backend MVC/Blade: xem [trạng thái hiện tại](docs/DELIVERY_STATUS.md), [cài/chạy](docs/DEPLOYMENT.md), [bàn giao FE](docs/FRONTEND_HANDOFF.md) và [minh chứng cuối](docs/verification/final/README.md). Chỉ đạo mới cho phép hoàn thiện toàn bộ, các ghi chú dừng duyệt phía dưới là lịch sử.

# Hệ thống quản lý công việc — Laravel MVC

Laravel 12, schema V3 với 20 bảng nghiệp vụ. Phần 1–5 đã duyệt; phần 6 Dashboard/report, queue và nền AI có backend/Blade chạy thật, chờ duyệt. Provider AI thật/Plan phần7 chưa triển khai.

## Tài liệu

- [Tiến độ](docs/IMPLEMENTATION_PROGRESS.md)
- [Flow phần 6](docs/STAGE_06_REPORT_QUEUE_AI.md)
- [Minh chứng phần 6](docs/verification/stage06/README.md)
- [Flow phần 5](docs/STAGE_05_CALENDAR_NOTIFICATIONS.md)
- [Minh chứng phần 5](docs/verification/stage05/README.md)
- [Flow phần 4](docs/STAGE_04_TASK_WORKFLOW.md)
- [Minh chứng phần 4](docs/verification/stage04/README.md)
- [Flow phần 3](docs/STAGE_03_PROJECT_TASK.md)
- [Minh chứng phần 3](docs/verification/stage03/README.md)
- [Flow phần 2](docs/STAGE_02_AUTH_WORKSPACE.md)
- [Bàn giao giao diện](docs/FRONTEND_HANDOFF.md)
- [Minh chứng HTTP/browser/SQL](docs/verification/stage02/README.md)
- [Database phần 1](docs/STAGE_01_DATABASE.md)
- [Kế hoạch tổng thể](docs/planning/KE_HOACH_TRIEN_KHAI_LARAVEL_MVC_V3.md)

## Cài trên máy mới

PHP 8.2+, Composer, extension Laravel và pdo_sqlite. Cài dependency theo composer.lock:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File -Path database/work_management.sqlite
```

Chỉ copy .env/tạo key/tạo file DB trong lần cài mới. Không ghi đè .env, không thay APP_KEY hoặc reset database đang dùng. Trong .env đặt DB_CONNECTION=sqlite và DB_DATABASE là đường dẫn tuyệt đối tới file SQLite, dùng dấu `/` trên Windows. Đặt SESSION_DRIVER=database, INVITATION_CACHE_STORE=file, MAIL_MAILER=log, APP_URL=http://127.0.0.1:8123 cho local này.

```powershell
php artisan config:clear
php artisan migrate
php artisan db:seed
php artisan serve --host=127.0.0.1 --port=8123
```

Mở http://127.0.0.1:8123/login. Demo local: demo1@example.test … demo5@example.test / Demo@123456. Seeder lặp được, chỉ local và không gọi dịch vụ ngoài. Không dùng migrate:fresh/rollback trên DB cần giữ. Máy hiện tại đã có APP_KEY/database/dependency; chỉ cần chạy server nếu chưa chạy.

Các trang phần 2–3 dùng CSS tĩnh, chưa cần Node build. Script Composer setup/dev mặc định Laravel có npm/npx/Pail, không phải lệnh chuẩn đã xác minh trên Windows này. Khi FE thêm build pipeline phải thống nhất lockfile và tài liệu.

## Cấu hình lời mời và môi trường

Local dùng file cache TTL/lock tường minh vì chưa có Redis server; Predis đã được cài. Production phải có Redis thật, REDIS_CLIENT=predis và INVITATION_CACHE_STORE=redis. Không có fallback âm thầm. INVITATION_TTL_MINUTES mặc định 1440, tối đa 10080. TTL thật đã được kiểm tra bằng file mode; Redis còn cần kiểm tra riêng.

MAIL_MAILER=log chỉ ghi email vào storage/logs, không gửi hộp thư. Key/SMTP và AI/integrations cấu hình sau; chưa có quên mật khẩu/email verification qua email. Không commit .env/APP_KEY/SQLite/cookie/token. APP_URL phải đúng host/port khi tạo link qua CLI/queue.

DB local đã xác minh là SQLite. MySQL/MariaDB, Redis thật, SMTP và production chưa được xác minh. Dữ liệu AI fixture có nhãn mô phỏng, không có gọi AI thật.

## Xác minh

Theo chỉ đạo chủ dự án: không thêm test class/mocks. Phần 2 chạy real HTTP với cookie/CSRF, đọc lại SQL và thao tác browser; bằng chứng/source tại docs/verification/stage02. File walkthrough ghi dữ liệu demo thật, đọc nội dung trước khi chạy lại. Không dùng test suite mặc định làm căn cứ nghiệm thu nghiệp vụ.

## Server local phần 4 và file

Để nhận DOCX/XLSX cần PHP ZIP. Helper `pwsh -File scripts/serve-local.ps1` dùng router Laravel, bật extension ZIP và giới hạn upload 6M/post 7M cho app limit 5 MiB; không đổi php.ini. Dừng server cũ cùng port trước, không chạy song song trên 8123. Nếu ZIP đã bật sẵn trong php.ini, bỏ flag extension trùng khi chạy PHP trực tiếp.

Task files ở storage/app/private/task-files, không chạy storage:link để public thư mục này. Chỉ tải qua route có auth/policy; gỡ attachment là soft-delete, bytes giữ private cho audit.


## Calendar, cấu hình và thông báo (phần 5)

Từ Workspace mở Lịch; từ Project mở Timeline. Lịch cho month/week/list, có form tạo/sửa/hủy và xóa theo quyền. Để xem dữ liệu review phần 5 chọn 07–14/12/2026, xem docs/verification/stage05/README.md. Trạng thái event không thay task status hay giờ thực tế.

Cấu hình và Thông báo ở thanh đầu trang. Thông báo trong ứng dụng tạo từ thao tác task thật; email/Telegram vẫn tắt. Giờ mặc định nằm trong config/work_schedule.php; language/theme/in_app trong user_settings; múi giờ ở hồ sơ. Không cần thêm key hoặc migration để dùng phần 5. Dừng chờ duyệt trước phần 6.


## Dashboard/report và queue AI (phần 6)

Từ Workspace hoặc Project mở Dashboard/báo cáo. Chọn kỳ [from,to), phạm vi được xem rồi lưu snapshot, xem bản in hoặc tải CSV. Snapshot là số liệu cố định, chỉ người tạo còn quyền trên nguồn mới đọc được; không sửa bằng dữ liệu AI.

Yêu cầu AI nhận xét chỉ được đưa vào queue work-management-ai, không gọi provider trong request web. Chạy `pwsh -File scripts/work-ai.ps1 -Drain` để xử lý đến khi hàng đợi riêng trống, hoặc bỏ -Drain để worker chờ job mới. Tương đương: `php artisan queue:work database --queue=work-management-ai --stop-when-empty --sleep=1 --tries=3 --timeout=45`.

Hiện AI_ENABLED mặc định false và adapter unconfigured: worker ghi failed rõ, không có nhận xét/tokens giả. Chọn provider/adapter/key sau; bật cờ một mình chưa tạo tích hợp thật. Không gửi key từ FE. Không cần migrate/Redis/SDK mới để dùng Dashboard/report và chạy queue lỗi cấu hình này.

Windows hiện không có pcntl_alarm; không coi hard timeout worker là đã kiểm chứng. Adapter thật phải có network timeout và production giám sát process. `php artisan ai:recover-stale` dùng cho run running bỏ dở quá180s; đọc tài liệu trước khi vận hành. Chưa có provider-success, crash/timeout thực hay Plan apply được xác minh.

Minh chứng/bộ dữ liệu phần6 ở docs/verification/stage06/README.md. Không chạy lại checkpoint scripts tùy tiện. Dừng chờ duyệt trước phần7.

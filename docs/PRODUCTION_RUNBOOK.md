# Runbook production — 23/10/2026

## Trạng thái và phạm vi

Bản này giữ Laravel 12 MVC/Blade; không yêu cầu SPA/API riêng hay npm build. Chưa triển khai lên máy chủ công khai. Chủ dự án chưa xác nhận hệ điều hành, database, domain, SMTP và Redis thực tế. Mẫu trong `deploy/` minh họa Linux + PHP-FPM + Nginx + Supervisor; không phải cấu hình đã cài hoặc đã nghiệm thu trên VPS.

Không đưa nguyên `.env` local hoặc database demo lên production. Không chạy `setup-local.php` ở production: helper này đã chặn ngoài local/testing. Không chạy `migrate:fresh`, `rollback` hay tạo lại APP_KEY trên dữ liệu đang sử dụng.

## 1. Chuẩn bị hạ tầng

- PHP tương thích composer.lock và các extension; worker Linux cần pcntl để thực thi timeout. Chạy `composer check-platform-reqs --no-dev` trên chính máy chủ. Không lấy kết quả XAMPP Windows để chứng minh Linux/MySQL đã đạt.
- Web root chỉ trỏ đến `public/`. User web/worker được ghi `storage/`, `bootstrap/cache/`; không cấp quyền ghi source hoặc `.env` nếu không cần. `.env`, log, backup, Redis và database không công khai.
- Chọn database trước migration. SQLite chỉ phù hợp một máy chủ, storage local và mức ghi phù hợp; chưa có kết quả chịu tải. MySQL/MariaDB cần chạy migration, bộ flow và backup/restore trên đúng phiên bản đích trước mở cửa.
- Redis dùng cho invitation records/locks. CACHE_STORE dùng database hoặc Redis chia sẻ; không dùng array/file trên nhiều instance. Session phải dùng bảng `sessions` trên database chính vì luồng đổi mật khẩu/đổi email/reset chủ động thu hồi session tại đây.
- SMTP thực hoạt động; domain/sender hợp lệ, cấu hình SPF/DKIM/DMARC theo nhà cung cấp. Gửi và nhận thư xác minh/reset bằng hộp thư thật trước khi mở đăng ký. Mail log chỉ dùng local; log local chứa liên kết nhạy cảm.
- HTTPS hợp lệ, cấu hình proxy chính xác. TRUSTED_PROXIES chỉ chứa IP/CIDR proxy thực, không dùng `*`. APP_URL là origin chính xác. Host khác bị từ chối; chuyển domain cần cập nhật APP_URL, config cache và worker.

## 2. Cài release và cấu hình

1. Tạo thư mục release mới; đặt source đã kiểm tra vào đó. Dùng cùng composer.lock:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
composer audit --locked
```

2. Tạo `.env` riêng từ `deploy/production.env.example`; thay toàn bộ placeholder. Template không chứa key/secret và không được dùng nguyên trạng. APP_ENV=production, APP_DEBUG=false, APP_URL=https://domain-thật, REQUIRE_VERIFIED_EMAIL=true, SESSION_DRIVER=database, SESSION_ENCRYPT=true, SESSION_SECURE_COOKIE=true.
3. APP_KEY: chỉ tạo khi cài hệ thống mới hoàn toàn chưa có dữ liệu mã hóa. Nếu cập nhật/khôi phục, lấy đúng key cũ từ secret store. Mất key sẽ mất khả năng đọc credentials mã hóa; backup database không thay thế key.
4. Bản cập nhật: vào maintenance, dừng scheduler và worker của chính ứng dụng, chờ request đang xử lý kết thúc; tạo backup đã kiểm chứng. Không để release cũ và mới ghi cùng lúc khi migration không tương thích.

```bash
php artisan down --retry=60
# Dừng hai program Supervisor và scheduler timer của ứng dụng trước backup.
# Tạo backup bằng quy trình phù hợp database ở mục 5.
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan app:preflight --production
```

5. Nếu preflight báo lỗi, sửa cấu hình/adapter/quyền trên máy chủ và chạy lại. Không đổi check thành PASS để vượt chặn. Check không in secret, không xóa cache toàn cục, chỉ đặt/xóa các cache probe ngẫu nhiên và lock của chính nó.
6. Cấu hình Nginx từ mẫu: đổi domain/chứng chỉ/socket PHP/path; kiểm tra `nginx -t` trước reload. `deploy/nginx.conf.example` cần được include ở http context vì có map/log_format. Access log đã loại query string và che path token reset/invitation/verification; kiểm tra thêm log reverse proxy/WAF/error log, phân quyền và retention.
7. Mẫu service chỉ là file `.example`; phải cài đúng vị trí, tên và quyền bằng cơ chế quản trị host. Mỗi queue có worker riêng để AI không làm chậm nhắc việc.

## 3. Worker, scheduler và kiểm tra sau cập nhật

Worker đang dùng **database connection** cho tính nguyên tử với transaction nghiệp vụ, dù Redis dùng cho cache. Không tự đổi sang `queue:work redis` mà không đổi hợp đồng enqueue và kiểm tra lại.

```bash
php artisan queue:work database --queue=work-management-ai --sleep=3 --tries=3 --timeout=45 --max-time=3600
php artisan queue:work database --queue=work-management-reminders --sleep=3 --tries=3 --timeout=45 --max-time=3600
```

Dùng Supervisor/systemd để giữ tiến trình. DB_QUEUE_RETRY_AFTER=180, lớn hơn timeout45. Sau release, restart worker có giám sát để dùng mã mới. Chỉ chạy **một** scheduler cho cụm hiện tại; mẫu timer chạy `schedule:run` mỗi phút. Không đồng thời cài cả cron và systemd timer.

- Mỗi phút: `ai:recover-stale`, heartbeat scheduler.
- Mỗi giờ: `tasks:remind`, `auth:clear-resets`.
- Queue Looping ghi heartbeat cho từng connection/queue. Heartbeat thể hiện hoạt động gần đây, không đảm bảo worker sẽ còn sống hoặc mọi job đều thành công.

Sau chuyển release, khởi động lại worker/scheduler, mở ứng dụng và kiểm tra:

```bash
php artisan up
php artisan app:preflight --production --runtime --json
php artisan schedule:list
php artisan queue:failed
```

Theo dõi preflight định kỳ từ hệ thống giám sát, cảnh báo khi exit code khác0. `/up` chỉ là liveness Laravel; không thay thế preflight runtime, kiểm tra DB, SMTP hay backup. Cần giám sát HTTP5xx, queue chờ lâu/thất bại, dung lượng đĩa, lỗi mail và độ tuổi backup. Không có tích hợp cảnh báo ngoài được tuyên bố là đã kết nối.

## 4. Nghiệm thu trên môi trường đích trước khi mở rộng người dùng

Chạy trên database staging riêng cùng engine/version với production:

1. Đăng ký → nhận thư thật → xác minh → workspace cá nhân; truy cập nghiệp vụ trước xác minh phải bị chuyển về hướng dẫn.
2. Quên mật khẩu → nhận thư → đổi mật khẩu → phiên khác mất quyền; liên kết cũ/hết hạn và tài khoản disabled không đổi được mật khẩu.
3. Owner mời người dùng; họ nhận mail, vào workspace/project đúng quyền. Member/Viewer không đọc tài nguyên ngoài phạm vi, kể cả bằng URL trực tiếp.
4. Tạo Project → GROUP/WORK → giao người/reviewer → IN_PROGRESS → timer → REVIEW → DONE; thử blocker/FS/lag và tab sửa cũ. Đối chiếu SQL và activity.
5. Calendar/Timeline đúng timezone; lịch riêng chỉ lộ busy theo quyền; sửa stale/conflict không ghi đè.
6. Plan draft → preview → propose → approve → apply; apply lặp không nhân đôi, nguồn stale bị chặn. Report snapshot giữ số liệu cũ sau chỉnh giờ.
7. Upload/download file private; người khác bị từ chối. Đổi quyền khi reminder còn queued phải bị worker kiểm lại.
8. Kiểm tra tải đồng thời tương ứng số người dùng dự kiến; đo truy vấn chậm, p95, lock contention, timeout. Máy dev đơn tiến trình không phải bằng chứng tải production.
9. Thực hiện restore trên một máy/DB riêng, đăng nhập, mở báo cáo, tải file, giải mã credential; kiểm tra scheduler/worker sau restore.

AI và GitHub giữ tắt đến khi cấu hình xong. AI phải kiểm tra provider thật với dữ liệu review đã được phép chia sẻ; GitHub phải xác nhận delivery thật đến HTTPS webhook. Thử HMAC loopback không chứng minh mạng công khai hoạt động. Không bật cờ để che lỗi cấu hình.

## 5. Backup và phục hồi

Một bộ phục hồi cần: database, private task files, đúng source/composer.lock, APP_KEY cũ từ kho secret riêng; cấu hình khác được quản lý riêng. Redis chứa lời mời đang chờ: cần persistence/backup phù hợp hoặc chủ động phát hành lại lời mời sau restore. Không sao chép cache/worker heartbeat rồi xem đó là bằng chứng dịch vụ đã chạy.

### SQLite

Dừng ghi như mục2. Chọn thư mục mới ngoài source, ngoài web root và ngoài vùng private task files; thư mục cha phải tồn tại và chỉ operator truy cập:

```bash
php artisan app:backup-sqlite /srv/backups/work-management/snapshot-YYYYMMDD-HHMM --workers-stopped
```

`--workers-stopped` là xác nhận trách nhiệm operator, lệnh không tự phát hiện hết tiến trình trên các máy khác. Lệnh yêu cầu maintenance, không ghi đè backup cũ, dùng VACUUM INTO, copy task-files, kiểm tra SQLite integrity/FK, đối chiếu file đính kèm theo hash và ghi manifest khi hoàn tất. Một thư mục không có manifest hoàn tất không được dùng làm backup đạt.

Khôi phục vào vị trí mới: đối chiếu mọi SHA256 trong manifest, copy database và task-files, cấp quyền dịch vụ, đặt DB_DATABASE mới và khôi phục đúng APP_KEY từ kho riêng. Không mở ngay ra công khai. Chạy kiểm tra application/HTTP/file/decrypt trước; chỉ chuyển traffic khi đã xác nhận kết quả. Bản diễn tập local đã thực hiện đúng trình tự này, nhưng không thay cho diễn tập VPS.

### MySQL/MariaDB

Chưa có kết quả nghiệm thu trên engine đích. Dùng công cụ phù hợp phiên bản và account backup hạn quyền, credential file riêng mode0600; không đặt mật khẩu trong command line hoặc Git. Maintenance và dừng writer vẫn cần để snapshot database đồng bộ với file.

```bash
# Ví dụ; thay tên database/path và xác nhận phiên bản công cụ trước khi dùng.
mysqldump --defaults-extra-file=/etc/work-management/backup.cnf --single-transaction --routines --triggers --events work_management > /srv/backups/work-management/database.sql
```

Sao lưu private task-files cùng thời điểm, tạo SHA256 manifest cho dump và file. Restore dump vào **database mới riêng** bằng client tương thích, không import vào database đang chạy để thử nghiệm. Thực hiện toàn bộ bước kiểm tra như SQLite. Mã nguồn có migration đa driver không có nghĩa flow MySQL đã được chứng minh.

### Rollback release

Giữ release trước và backup trước migration. Nếu chỉ đổi mã tương thích schema, vào maintenance, dừng worker, chuyển về source trước, rebuild config/route/view cache, restart worker rồi kiểm tra. Nếu đã migration không tương thích, không chạy rollback tùy tiện: phục hồi DB/file/key vào vị trí riêng, kiểm chứng, rồi chuyển cấu hình/traffic. Ghi nhận dữ liệu phát sinh sau backup và quyết định phục hồi với chủ dữ liệu.

## 6. Những thông tin còn cần chốt

Hệ điều hành/hosting; database và version; domain/HTTPS; SMTP; Redis; vị trí lưu file/backup; người nhận cảnh báo; số người dùng và yêu cầu RPO/RTO. Chưa có những thông tin này nên chưa thể phát hành kết luận GO cho môi trường thật.

Tham chiếu theo framework đang dùng: [Deployment Laravel12](https://laravel.com/framework/docs/12.x/deployment), [Queues](https://laravel.com/framework/docs/12.x/queues), [Scheduling](https://laravel.com/framework/docs/12.x/scheduling), [Password reset](https://laravel.com/framework/docs/12.x/passwords), [Email verification](https://laravel.com/framework/docs/12.x/verification).

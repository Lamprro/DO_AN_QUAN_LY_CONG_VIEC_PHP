# Cài và vận hành

## Cài local mới

PHP8.2+, Composer; các extension Laravel cần, pdo_sqlite, mbstring, openssl, fileinfo; ZIP cho tệp Office. Có thể dùng PHP XAMPP đã cài. Source dùng Blade/CSS/JS tĩnh nên không cần npm/Vite để chạy giao diện bàn giao.

```powershell
composer install
php scripts/setup-local.php --demo
php scripts/serve-local.php
```

`--demo` chỉ dùng local/demo, tạo demo1..demo5@example.test / Demo@123456 cùng dữ liệu giả gắn nhãn. Không seed tài khoản công khai lên production. Nếu không muốn demo bỏ --demo và tự đăng ký. Helper chỉ sinh APP_KEY khi trống, chỉ migrate bổ sung, không reset DB. Khi đã có .env, helper giữ cấu hình và key. Không dùng key:generate để sửa lỗi giải mã dữ liệu cũ.

Nếu php/composer chưa có PATH, dùng `C:\xampp\php\php.exe` và `C:\xampp\php\php.exe D:\Composer\composer.phar install`. Terminal hai chạy queue: `php artisan queue:work database --queue=work-management-ai --sleep=1 --tries=3 --timeout=45`; Windows có scripts/work-ai.ps1 [-Drain]. Worker phải chạy để xử lý AiRun; AI tắt sẽ ghi failed có lý do thay vì sinh giả.

Local tại http://127.0.0.1:8123. .env.example dùng SQLite, database sessions/cache/queue, file TTL invitations và mail log. Helper tạo file SQLite nếu chưa có. DB_DATABASE cần đúng đường dẫn tuyệt đối nếu đổi chỗ dự án. Không public toàn bộ thư mục gốc; web root chỉ public/. Attachment lưu private, tải qua controller kiểm quyền, không tạo symlink public cho task_files.

## Cập nhật cài đặt đang có

Sao lưu .env/APP_KEY, database và storage/app/private trước cập nhật. Giữ đường dẫn DB cũ. composer install → php artisan migrate --force → php artisan view:clear → php artisan config:clear → khởi động lại worker. Không migrate:fresh/rollback, không ghi đè key, không đổ DB demo đè DB thật. Thử đăng nhập, một task, private download, lịch, snapshot và worker sau cập nhật.

## Máy chủ production

Web root public/, HTTPS, APP_ENV=production, APP_DEBUG=false, APP_URL đúng domain, session secure cookie qua SESSION_SECURE_COOKIE=true. Thư mục storage/ và bootstrap/cache/ được phép ghi cho web user, source khác chỉ đọc. Composer install --no-dev --optimize-autoloader. Không đưa .env/vendor credentials/private DB vào public/Git/ZIP nộp.

Production invitations bắt buộc Redis: INVITATION_CACHE_STORE=redis và REDIS_HOST/PORT/PASSWORD thật; kiểm TTL, nhận/thu hồi lời mời bằng tài khoản đúng email. SMTP thật cần MAIL_*; local chỉ mail log. Nếu chạy nhiều web/worker instance, dùng cùng DB/Redis và private storage được chia sẻ phù hợp; SQLite hiện chỉ xác minh local, chưa đánh giá tải nhiều máy.

Dùng dịch vụ quản lý worker (Supervisor/systemd hoặc Windows service), database queue work-management-ai, tối đa3 lượt, timeout45/retry_after180. Không dùng queue:clear tùy tiện. Theo dõi jobs/failed_jobs/AiRun/AiCall. Khi đổi code/config chạy queue:restart hoặc restart service. Chạy `php artisan ai:recover-stale` định kỳ khoảng một phút bằng scheduler hệ điều hành để đóng request running quá lease180s; sau đó người có quyền retry nếu còn lượt. Lệnh recovery không chứng minh job/provider thành công. Windows không có pcntl_alarm; HTTP adapter tự timeout30s nhưng hard-kill worker chưa được xác minh.

GitHub: bật INTEGRATIONS_ENABLED chỉ sau cấu hình thật; Owner vào project → GitHub logs nhập repository ID và secret entropy cao ≥32 ký tự, bật connection, khai webhook URL trên GitHub trỏ HTTPS. Chọn JSON content type, push (ping khi tạo). Không bỏ kiểm chữ ký hoặc mở CSRF toàn ứng dụng: chỉ /hooks/github/* dùng HMAC thay CSRF. Secret mã hóa bằng APP_KEY. Nhận trùng delivery không thêm log/activity. Kiểm request signed local trong final/github-flow.json không đồng nghĩa GitHub public đã nối thành công. Quy tắc chữ ký theo [GitHub Docs](https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries).

AI: xem AI_PROVIDER_CONTRACT.md; mặc định tắt, không tự chọn dịch vụ trả phí. Các khóa đặt server-side sau; không đưa vào input/Blade. Chưa có key thì dùng plan manual/local scheduling để kiểm trọn luồng.

## Sao lưu/khôi phục

Sao lưu nhất quán DB cùng private storage và APP_KEY; giữ secret ngoài source ZIP. Khôi phục vào thư mục/database mới trước, trỏ .env đúng bản sao, giữ key, migrate bổ sung nếu cần, kiểm đăng nhập/file/lịch/snapshot và worker. Chưa thực hiện disaster recovery trên máy thứ hai. Thư mục dist là gói source, không phải backup dữ liệu.

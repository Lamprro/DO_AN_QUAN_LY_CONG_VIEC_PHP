**23/10/2026 — F01–F15:** F11–F14 đã bổ sung, xem [báo cáo](docs/FULL_FUNCTIONAL_COMPLETION_2026-10-23.md). Review35bảng đã kiểm; migration database chính còn chờ xác nhận.

# Work Management — Laravel MVC/Blade

Quản lý công việc cá nhân và nhóm: workspace/role/member, project, cây task và review, dependency/blocker, calendar/timeline, plan có duyệt, timer, báo cáo và thông báo. Laravel12/PHP≥8.2; schema hiện hành31 bảng. Giao diện Blade, form web/session/CSRF; không cần SPA/API riêng hoặc npm build.

**Trạng thái mới nhất:** nghiệp vụ cốt lõi và các sửa lỗi có bằng chứng trên bản cài riêng; chưa nghiệm thu hạ tầng production. Không đưa database demo hoặc .env local lên public.

## Tài liệu cần đọc

- [Báo cáo toàn dự án và điều kiện còn thiếu](docs/PRODUCTION_READINESS_REVIEW_2026-10-23.md)
- [Runbook production, vận hành, backup/restore](docs/PRODUCTION_RUNBOOK.md)
- [Luồng sử dụng](docs/USER_WORKFLOW_GUIDE.md)
- [Bàn giao AI frontend](docs/FRONTEND_HANDOFF.md)
- [Thiết kế/ERD/schema hiện hành](docs/design/CURRENT_SYSTEM.md)
- [Minh chứng flow thực mới nhất](docs/verification/production-readiness-2026-10-23/README.md)
- [Tiến độ và lịch sử triển khai](docs/IMPLEMENTATION_PROGRESS.md)
- [Kế hoạch gốc và tiêu chí đồ án](docs/planning/KE_HOACH_TRIEN_KHAI_LARAVEL_MVC_V3.md)
- [Cài đặt local và chi tiết triển khai](docs/DEPLOYMENT.md)

## Cài mới trên local

```bash
composer install
php scripts/setup-local.php --demo
php scripts/serve-local.php 8123
```

Helper tạo .env/SQLite/key khi chưa có, migrate và tùy chọn seed; không reset database hoặc xoay key đã có. Chỉ cho phép local/testing. Với bản có dữ liệu, sao lưu và đọc deployment trước khi cập nhật; không dùng migrate:fresh.

Demo local: demo1@example.test đến demo5@example.test, mật khẩu Demo@123456. DemoSeeder bị chặn ở production. Các tài khoản này không được dùng trên hệ thống thật.

## Queue và lịch local

Chạy ở các terminal riêng:

```bash
php artisan queue:work database --queue=work-management-ai --tries=3
php artisan queue:work database --queue=work-management-reminders --tries=3
php artisan schedule:work
php artisan app:preflight --runtime
```

AI và tích hợp ngoài mặc định tắt; ứng dụng không tạo kết quả provider giả. SMTP log chỉ phục vụ local. Muốn thử flow xác minh local, đặt REQUIRE_VERIFIED_EMAIL=true; liên kết thư nằm trong log riêng, không chia sẻ hoặc commit log.

## Production

Dùng `deploy/production.env.example` như danh sách cấu hình, thay toàn bộ placeholder. Cài worker/scheduler có giám sát và SMTP/Redis thật; chạy:

```bash
php artisan app:preflight --production --runtime
```

Mã0 chỉ xác nhận các check hiện có, không thay nghiệm thu HTTPS/mail/load/restore trên host đích. `/up` là liveness đơn giản. Runbook ghi rõ quy trình backup, cập nhật và rollback. Các mẫu `deploy/*.example` chưa được cài lên máy chủ nào.

## Gói nguồn và dữ liệu

```bash
python scripts/package-source.py
```

Gói `dist/work-management-mvc-source.zip` có manifest, source/docs/templates; không gồm vendor, .env, key, database hoặc private uploads. Cần composer install ở đích. Gói nguồn không phải backup dữ liệu. Dùng `app:backup-sqlite` theo runbook cho SQLite; MySQL/MariaDB cần backup/restore theo engine thực.

Không thêm TestCase theo yêu cầu dự án. Các walkthrough trong docs/verification thực hiện form HTTP/cookie/CSRF, đọc DB, file và worker trên môi trường riêng; không chạy chúng trên production.

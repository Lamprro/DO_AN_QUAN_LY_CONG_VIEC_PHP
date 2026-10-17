# Xác minh phần 2 — flow thật

Ngày 17/10/2026. Server Laravel thật tại 127.0.0.1:8123, SQLite work_management.sqlite, session database, invitation cache file có TTL/lock, mail transport log. Không thêm TestCase/mocks, không chạy suite mặc định để nghiệm thu.

## Bằng chứng

- `http-walkthrough.py`: thao tác tuần tự bằng Python stdlib urllib + CookieJar, đọc form CSRF/action từ HTML thật, gửi tới route đã implement và truy vấn SQLite read-only sau các thay đổi. Đây là script chạy flow trực tiếp, không thay Controller/Service/DB bằng mock. Dừng nếu HTTP khác kỳ vọng.
- `http-flow.json`: kết quả thực chạy; có final URL, HTTP, lỗi hiển thị và dữ liệu DB đọc lại. Status 200 của validation là trang form sau redirect, phải đọc errors và dữ liệu DB kèm theo, không coi mọi 200 là thành công nghiệp vụ.
- `targeted-walkthrough.py`, `targeted-flow.json`: HTTP bổ sung cho email trùng/đổi email, mật khẩu sai kiểu hoặc sai current_password, bảo vệ workspace cá nhân, tài khoản disabled trong phiên hiện có và rate limit đăng nhập. Trạng thái disabled được đổi qua Artisan trên tài khoản review rồi khôi phục active trong finally; không có trang quản trị user trong phạm vi này.
- `expiry-issue.tinker.txt`, `expiry-flow.json`: gọi InvitationService thật để tạo link TTL 60 giây, HTTP GET trước/sau thời gian hết hạn tự nhiên. Token chỉ lưu private local, không đưa vào bằng chứng công khai.
- `routes.json`: inventory xuất từ `php artisan route:list --except-vendor --json`.
- `workspace-browser.png`: browser thực đăng nhập demo1, gửi form tạo team workspace “Review Phase 2 Browser”, xem trang sau redirect. SQL xác nhận đúng owner, 4 roles và 1 member. Không phải ảnh prototype.
- `technical-checks.txt`: lint PHP, Composer validate, Blade compile và Git whitespace check cuối phần.

## Luồng đã quan sát

| Luồng thực tế | Kết quả |
|---|---|
| Guest truy cập trang bảo vệ; login/logout | Redirect login; tạo/hủy session thực |
| Register email viết hoa, kèm field status giả mạo | Email chuẩn hóa; active từ server; có personal workspace, Owner và UserSetting |
| Tạo/sửa workspace; gửi owner/kind giả | Team/owner lấy từ server; DB đọc lại đúng; trường giả không được ghi |
| POST thiếu CSRF; name rỗng/timezone sai | 419; validation tiếng Việt và không tạo bản ghi sai |
| Người ngoài truy cập workspace; role của workspace khác | 403 và 404 theo boundary thực tế |
| Tạo/sửa/xóa role; sửa system role; cấp quyền vượt actor | CRUD role hợp lệ; system role 403; cấp vượt quyền bị validation, DB không có role sai |
| Xóa role đã gán và role không được dùng | Role đang dùng giữ nguyên; role trống được xóa |
| Invite → xem link → accept đúng/sai email | Chưa accept thì chưa có membership; sai email bị từ chối; đúng email thêm membership active |
| Dùng lại/thu hồi/hết hạn lời mời | 410; không tạo trùng membership |
| Suspended → active; remove → left → re-invite | Phiên hiện tại bị 403 khi suspended/left; hoạt động lại sau active; rejoin giữ membership UUID |
| Gỡ Owner/Owner rời khi chưa chuyển chủ | Bị từ chối, DB Owner giữ active |
| Profile và password | Cập nhật hồ sơ; password cũ không đăng nhập được, password mới được; session thứ hai bị thu hồi |
| Transfer Owner | owner_id và hai role đổi cùng transaction; chủ cũ mất quyền Owner, chủ mới có quyền |
| Tạo rồi xóa workspace team trống | Xóa thật, SQL không còn workspace đó |
| Foreign key check sau flow | PRAGMA foreign_key_check không có dòng lỗi |

Canonical HTTP run có workspace `Phase2 HTTP Reviewed 1792209594`, ID `01a14804-35da-70e4-9c7e-553213e17fd3`. Chủ mới là tài khoản review .test được tạo trong luồng; demo1 thành Manager trên riêng workspace review này. Các fixture seeder gốc được giữ. Có lần walkthrough trước và workspace browser để lại dữ liệu review, không xóa dữ liệu nghiệp vụ để làm sạch minh chứng.

Cookie/state/link token nằm tại storage/app/private/stage02-review, bị Git ignore. Không đính kèm thư mục private vào gói nộp. Log mail local cũng có link mời, không công khai log thô.

## Giới hạn

Các flow trên chứng minh môi trường local và route hiện có, không phải chứng nhận mọi race condition hoặc mọi cấu hình triển khai. Chưa chạy Redis/SMTP/MySQL thật, tải nhiều request đồng thời, email verification/reset password, hay workflow project/task của phần 3. Lời mời local file có TTL thật; không báo Redis đã được kiểm tra. Mật khẩu dùng trong script là tài khoản demo .test, không phải credential người dùng thật.

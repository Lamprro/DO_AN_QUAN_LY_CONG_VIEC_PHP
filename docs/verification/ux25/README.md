# Kiểm chứng 25/10/2026

Đây là nhật ký HTTP/database thật và thao tác trình duyệt. Không thêm TestCase hoặc mock.

- `flow-panels.json`: tạo, sửa, 422, 409, đối chiếu, UTC, blocker, Viewer/cross-scope, escaping và 31 trang.
- `flow-services.json`: watcher, timer, điều chỉnh phiên, work-hours version, upload/download và history.
- `flow-concurrent-browser.json`: phiên HTTP thứ hai cập nhật khi form trình duyệt đang mở.
- `browser-review.json`, `desktop.png`, `mobile.png`: quan sát giao diện và kết quả thao tác.
- `attempt-project-show.json`: lần phát hiện trang dự án 500 trước khi sửa; không tính là pass.
- `validation.json`, `release-checks.json`: kiểm tra mã/cache/dependency.
- `data-preservation.json`: so sánh dữ liệu chính, không chứa bản dump hoặc key.

Tái hiện: cài riêng với database review → migrate → DemoSeeder → đăng nhập demo → tạo dự án và công việc có người làm/người duyệt → thử các flow trên. Sử dụng hai phiên đăng nhập để kiểm tra stale. Bật MariaDB, giữ nguyên CSRF/revision do server cấp. Với timer, chờ ít nhất một giây giữa start/stop và đối chiếu started_at không đổi. Không dùng database chính để chạy seed hoặc reset.

Các lần thử script điều chỉnh giờ và lặp lại work-hours ban đầu có dữ liệu đầu vào không phù hợp; đã sửa script theo form thực tế. Trong quá trình này phát hiện và sửa lỗi ON UPDATE thật của MariaDB. Chỉ kết quả COMPLETE cuối của flow-services được xem là hồi quy đạt.

# Chạy lại trên bản review riêng

Các script này là walkthrough HTTP/CSRF/SQL/worker đã dùng trong vòng nghiệm thu; bản lưu thêm guard/env portable, chưa tuyên bố được chạy lại nguyên suite sau chuyển đường dẫn. JSON thư mục cha là log lần chạy gốc. Không thêm lớp test/mocks.

1. Giải nén source mới vào thư mục riêng ngoài repo; cài dependencies, cấu hình local/testing SQLite và SESSION_DRIVER=database. Chạy setup-local.php --demo trên bản mới. Không dùng DB chính.
2. Đặt WM_REVIEW_ROOT trỏ bản riêng; WM_REVIEW_URL=http://127.0.0.1:8128; WM_PHP trỏ PHP; WM_EVIDENCE_DIR trỏ thư mục riêng ngoài repo. Mở server review ở port đó. Có thể dùng port khác cho HTTP nhưng operations drill cần8131 trống.
3. Chạy python full_flow.py lần lượt setup, hours, team, templates, watchers. Demo1/demo3/demo4/demo5 có mật khẩu demo của seeder; không dùng tài khoản thật. Các ID được ghi private storage của review.
4. Chạy full_edges.py, full_parallel.py, full_guard_flow.py, full_cancel_flow.py. Dữ liệu có state và các mốc thời gian phục vụ kịch bản gốc; khi chạy vào ngày khác phải dời ngày tương lai của fixture, không sửa clock hay bypass validation của ứng dụng. Mỗi suite cần bản mới, không chạy lặp trên fixture bận cũ.
5. Chạy full_regression.py core, calendar-plan, reports-queue. Script operations dùng dữ liệu snapshot/attachment của regression và template của full_flow. Đọc full_operations.py rồi chạy trên review mới: nó đổi cấu hình, maintenance, queue, backup và restore riêng; không chạy trên production. Các thư mục private-snapshot-full/restored-full phải chưa tồn tại. Đây là diễn tập Windows/PHP/SQLite, không phải nghiệm thu MySQL/Linux/host thứ hai.

Script phụ không được dùng để ghi SQL thay flow. Hàm rows chỉ mở mode=ro. Không đưa private flow-state, cookies, .env hoặc SQLite vào Git/gói source. Xem ../README.md và báo cáo để phân biệt actual acceptance với attempt chưa đạt.

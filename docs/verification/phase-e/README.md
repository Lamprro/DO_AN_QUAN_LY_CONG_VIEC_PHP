# Minh chứng đợt E — flow và dữ liệu thật, 18/10/2026

Không thêm TestCase/PHPUnit/mocks. Đây là journal thao tác HTTP/session/CSRF, SQL read-back, worker database và browser trên app thật, có dữ liệu fixture rõ. Không gọi fixture là dữ liệu nghiệp vụ production. Source/logic tại [PHASE_E_UTILITIES.md](../../PHASE_E_UTILITIES.md).

## Dữ liệu và cách kiểm

Workspace `01a14a6e-ff85-7003-a4fa-4f4421c1931f`, project `01a14a6f-0362-706c-b894-285091fe2f26`; dùng các demo account có sẵn. Tiền tố mới `Eutil1792292478`:12 WORK,1 GROUP,1 task scope khác; không reset dữ liệu cũ hay cấp thêm role. Cookies/HTML/state/tokens chỉ lưu private runtime, không đóng gói. Các script là walkthrough thủ tục, không thay service nghiệp vụ và không tự ghi bảng task để ép flow đi qua.

| Bằng chứng | Nội dung |
|---|---|
| http-flow.json |12 WORK qua form thật; mine10+2; blocked/overdue/review; saved whitelist/private/wrong scope; bulk rollback cả history/notification khi policy/validation thất bại, positive priority/assign/archive, stale và Viewer; deadline reason/history; scan/dedupe; worker gửi thật và bỏ task đã cancel; Member operations403; own AI queue/disabled fail; hai timer start-wait-stop; overlap/foreign/running/stale; snapshot trước/sau giờ sửa; FK/.env/digest task cũ. |
| boundary-flow.json | Member giả request phân công403, private filter delete404; Owner xóa riêng filter fixture; nhắc đã queued→đổi assignee→worker skipped/no stale notification; sửa giờ tương lai, thiếu reason, timezone mismatch không ghi/audit. |
| technical.json | Pint/lint source và Blade đã biên dịch; route/config cache rồi clear; schedule/migrations; EXPLAIN index; audit model update/delete bị chặn trong transaction rollback bảo vệ; SQL xác nhận browser sửa note, audit2, thời gian không đổi; FK. |
| isolated-utilities.json | HTTP presets/reminders/operations, lưu/mở/xóa filter thật ở cả workspace cá nhân và project trên bản cài riêng; không ghi DB chính. |
| release-install.json | Giải nén source vào thư mục/database riêng, cài schema mới, seed demo hai lần không nhân đôi/đổi key, HTTP login và trang thật. Dùng vendor đã cài trên cùng máy; không tuyên bố composer tải qua mạng hoặc máy vật lý thứ hai. |
| e-saved-filter.png | Owner lưu filter tên “E — bộ lọc lưu trực tiếp từ Blade” bằng form trình duyệt. |
| e-time-adjustment.png | Form Blade lưu note/reason, hiển thị success và lịch sử trước/sau. SQL xác nhận audit thứ2, cùng start/end, note khác. Viewport319px/document304px tại lúc đo. |

F07 dùng `--review-after=0` cho REVIEW vừa tạo, không sửa clock/created_at SQL. Worker chỉ drain queue liên quan, không chạy lâu dài hay cài dịch vụ hệ điều hành. Nhắc hạn/review sent là Notification in-app được lưu, không phải SMTP hay thông báo trình duyệt bên ngoài.

F10 dùng thời gian do hai timer thật sinh ra; correction đầu giảm end1giây với reason rõ và bản gốc lưu trong audit; correction từ browser chỉ thay note. Snapshot cũ giữ nguyên, snapshot mới dùng actual đã sửa. Hai session lưu form cũ xác minh stale rejection; không mô phỏng race đa process.

## Lỗi đã gặp và sửa, không che bằng PASS

Lần mở saved filter ban đầu500 vì Laravel tìm Project::saveds theo inherited scoped binding. Đã bỏ scoped binding trên từng route saved open/delete và giữ exact scope check trong controller; chạy tiếp đúng các request thành công và request403/404. HTTP journal giữ quan sát lỗi.

Walkthrough từng dừng khi helper kỳ vọng200 cho bulk bị policy403 đúng; sửa assertion âm tính, không sửa app để nới quyền. Helper resume thiếu biến project riêng, rồi cross-scope fixture chưa tồn tại nên không thực sự kiểm được request âm tính; sửa helper tạo task thật qua form và gửi exact ID, kiểm404+rollback. Các resume ghi trong journal, không tính các lần helper dừng là bước đã PASS.

Helper EXPLAIN ban đầu dùng nhầm tên bảng blockers thay vì task_blockers; đã sửa và chạy tiếp đọc SQL, journal technical giữ bước resume. Các helper sinh mã/copy có assertion lỗi đã được chỉnh bằng delta; không lấy output helper làm bằng chứng ứng dụng thành công. Xem JSON và DB assertions sau lần sửa. Không sao chép toàn laravel.log có token/link runtime vào hồ sơ.

## Phạm vi chưa nghiệm thu

Chưa dùng quyền thực để thu hồi membership, thay preference của tài khoản cũ hoặc cấp quyền operations cho role khác; guard hiện tại có trong mã. Chưa thử kill worker giữa transaction, tải lớn, SMTP/network AI/GitHub, production cron hay MySQL server. Không dùng index để khẳng định đã tối ưu tải lớn; technical.json chỉ chứng minh planner đã chọn đường index với query hiện tại. Môi trường chính giữ .env/APP_KEY/flags và lịch sử có trước flow.

Walkthrough phụ thuộc fixture P0/private-state và demo local, không tự chạy trên production. `e_flow.py` là bản driver đã sửa; resume1–3 và boundary cho biết đường tiếp tục lần thực tế. Muốn lặp ở môi trường khác phải chỉ định fixture scope thích hợp; không copy private state/cookie vào repo. `release-install.py` là flow cài riêng có thể xem để tái hiện an toàn.

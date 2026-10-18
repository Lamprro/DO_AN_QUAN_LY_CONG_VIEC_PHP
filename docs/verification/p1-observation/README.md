# Minh chứng đợt D/P1 — 18/10/2026

Story: native Blade → web route/session/CSRF → Controller/Policy/Service → database thật → HTML/CSV → đọc lại SQL. Không TestCase, mock, timestamp giả hay reset database. Dùng demo1 Owner, demo3 Member, demo2 Viewer và demo5 ngoài workspace review P0 sẵn có, không tạo/gán role hay đổi membership.

- http-flow.json: Calendar4 chế độ, project filter, private busy,93ngày/403, prefill eligible, conflict/adjacent, dashboard/snapshot/print/CSV, actualtimer, notifications, workerAI. Lỗi so chuỗi CSV có quote được lưu verified=false; phần tiếp theo dùng CSV parser có PASS, không xóa dấu vết thất bại.
- boundaries.json: slot qua nửa đêm được clip30 phút từ nguồn60phút; all-day exclusive end; cancelled default/explicit; snapshot vẫn90phút dù thêm lịch; AI retry/cancel/worker không gọi provider hoặc tạo draft.
- technical.json: PHP nguồn thay đổi/Blade compiled, format, git diff check, formatter null/0/0.11/90 (ví dụ kiểm cách trình bày, không phải dữ liệu actual bịa), FK/env unchanged.
- p1-report.png: snapshot hiện90phút dự kiến dạng1giờ30phút, actualnull và bảng/chart trong IAB. Browser đã đăng nhập, nhấn ngày kế, đổi chế độ qua native GET; month grid1050px cuộn trong vùng272px, document304px/viewport319px; report cũng document304px, không tràn ngang toàn trang tại các trang kiểm.

Script p1_observation_flow.py là phiên đầy đủ (đã sửa parser CSV), p1_observation_resume.py dùng state để chạy tiếp lần dừng, p1_boundary.py kiểm biên. Cookie/response lỗi/state nằm storage/app/private/p1-observation, không đưa source. Script có mutation HTTP thật; chỉ dùng workspace review phù hợp, không chạy tùy tiện ở production. Trước requestAI xác nhận queue rỗng; worker --once chỉ xử lý job mới, không drain hàng đợi của dữ liệu khác. AI cờ tắt, GitHub không bật/gửi mạng; network thành công không được chứng minh ở lượt này.

Không có migration/dependency mới. Các dữ liệu trước đó và APP_KEY giữ nguyên; chỉ thêm fixture P1, sửa chính task P1 mới tạo, đổi trạng thái chính notification/run P1. Quyền hiện tại được giữ nguyên. Đọc P1_OBSERVATION.md về giới hạn trình duyệt/cơ sở dữ liệu và các phần đợtE còn lại.

Chốt kiểm tra:138 file PHP nguồn thay đổi và Blade đã biên dịch qua kiểm cú pháp, định dạng và Git diff check sạch; FK không lỗi, queue rỗng sau xử lý, không migration/test class mới. {'http-flow.json': 89, 'boundaries.json': 23} là số mục quan sát, gồm cả hành động/lỗi helper/xác minh, không phải số test độc lập.

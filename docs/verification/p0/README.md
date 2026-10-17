# Kiểm tra P0 bằng flow/dữ liệu thật

Môi trường: Laravel MVC/Blade, PHP8.2.12, SQLite hiện có, server http://127.0.0.1:8123. Dữ liệu review tách trong workspace **P0 UX Review 1792250134**. Không migrate:fresh, không test class/mock, không seed đè DB chính. Cookie/state ở storage/app/private/p0-review, không commit.

## Bằng chứng

- http-flow.json: walkthrough đầu tiên với đăng nhập/session/CSRF/form, optimistic Task/Project A/B, input lỗi, draft/preview/apply/replay/revision, blocker/FS/timer, Viewer/outsider, DB read-back và env hash. p0_http_walkthrough.py là script đã chạy.
- boundary-flow.json: kiểm tra sau polish: native expected_status raw/propose/approve/apply; advanced JSON; conflict muộn rollback; snapshot stale; source search và ancestor; counts không lộ project ẩn; chủ timer dừng sau mất membership; người khác403. p0_boundary_flow.py có URL stop đã sửa đúng `/timers/{entry}/stop`. Lần đầu helper dùng nhầm `/time-entries/...` bị404 rồi dừng; p0_boundary_resume.py tiếp tục trạng thái thật đã tạo, không sửa app để né lỗi. JSON hiện là phần chạy tiếp; các bước login/cấp fixture ban đầu có thể xem source và dữ liệu còn trên máy.
- browser-readback.json: SQL đối chiếu các payload đã lưu trực tiếp từ IAB, ref/thứ tự/cha/dependency/slot, task status và ended timer thật; kèm quan sát menu/focus/viewport và sửa source picker dòng mới đã kiểm lại.
- technical-checks.json và release-install.json: kết quả kiểm mã/cache và cài database riêng trên cùng máy (nếu bước có exit_code0). Không coi đây là cài trên máy vật lý thứ hai.
- p0-preview-final.png, p0-task-browser.png: ảnh IAB thực tế màn hình hẹp. p0-preview-mobile.png là ảnh vòng trước polish, lưu để đối chiếu.

Preview được kiểm bằng count+SHA của tasks/projects/plans/task_dependencies/calendar_events/task_activities/notifications/time_entries trước/sau request, cả thành công và conflict. Chỉ session/http metadata có thể đổi; nghiệp vụ không lưu. Không chỉ kiểm HTTP200 vì validation có redirect200: script đọc thông báo và SQL thật để kết luận.

Browser đã thao tác: tạo GROUP/WORK, FS+hai slot kề nhau; đổi tên và lên/xuống giữ ref; lưu nháp/preview; native status button; cây3 tầng; tìm nguồn vẫn giữ nội dung; thêm dòng sau tìm kiếm và chọn task nguồn; menu mobile mở/Escape trả focus. Ở viewport319px, đo documentWidth304px không có cuộn ngang toàn trang tại trang task đã kiểm. Chưa chứng nhận tất cả cỡ desktop/browser/reader chuyên dụng.

Một flow mở rộng tạo/gán custom role mới cùng lặp suspend/timer bị auto-review từ chối trước khi chạy. Không thực hiện lại bằng đường khác. Các kiểm quyền hiện có (Viewer/outsider/suspended và stop chủ timer) đã chạy ở walkthrough/boundary; source picker thêm guard task.view qua rà mã. Không ghi flow custom role là PASS. AI/provider/GitHub network chưa chạy vì chờ key; không đổi cờ để giả thành công.

Các script tạo dữ liệu review thật và có mutation HTTP, không phải công cụ nên chạy tùy tiện trên production. Bản resume chỉ dùng với state của lần đã chạy; muốn kiểm lại cần workspace review mới và quyền phù hợp. Không dùng file này để tự cấp quyền ngoài phạm vi người dùng cho phép.

## Chốt kết quả

319 file PHP nguồn và Blade đã biên dịch qua kiểm cú pháp; hai file JavaScript qua kiểm cú pháp; Composer hợp lệ; route/config cache tạo thành công rồi đã clear; migration revision có trạng thái Ran. Git diff --check đã sạch sau sửa khoảng trắng cuối dòng (log giữ cả lỗi trước sửa). Cài source/database riêng chạy migration, seed demo hai lần giữ key và đăng nhập HTTP thành công; dùng vendor đã cài cùng máy, không tuyên bố tải dependency lại qua mạng.

http-flow.json có 78 mục quan sát; boundary-flow.json có 45 mục trong phần chạy tiếp. Số mục gồm cả hành động và xác minh, không gọi đó là số test độc lập.

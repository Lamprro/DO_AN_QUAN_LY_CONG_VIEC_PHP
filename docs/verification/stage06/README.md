# Minh chứng phần 6 — Dashboard/report, queue và nền AI

17/10/2026. Không thêm/chạy TestCase, PHPUnit, mocks hoặc suite mặc định. Không migrate/reset/rollback, sửa APP_KEY/.env hay đổi đồng hồ. Flow chính đi qua form HTML/session/CSRF → middleware/controller/service → SQLite, worker database thật → Blade. Dữ liệu review phần1–5 giữ nguyên.

## Hồ sơ thực tế

| File | Kết quả và cách đọc |
|---|---|
| http-flow.json, before-csv-helper-fix.json | 44 quan sát, 10 đối chiếu true và1 assertion helper false. Auth/workspace/project/member/tasks/calendar/status → Dashboard/snapshot cho đúng4 WORK, estimate180, overdue1, DONE1, planned120 và actual null. Dừng ở helper so chuỗi `WORK DONE,1` trong CSV, trong khi fputcsv quote trường chứa khoảng trắng. Không có lỗi export ứng dụng. Giữ journal này, không gọi cả file là pass. |
| report-queue-flow.json | Sửa helper dùng csv.reader đọc CSV thật rồi tiếp tục checkpoint, không reset dữ liệu. 58 quan sát,25 verified=true, có complete marker. CSV formula-safe; period UTC cắt còn30 phút; snapshot/CSV giữ nguyên sau đổi estimate, bản mới240; project ngoài quyền không lộ; AiRun/job idempotency; worker lỗi cấu hình;3 lượt cap; cancel/no-op; worker kiểm quyền sau khi actor bị suspend; không task/calendar/plan bị AI sửa; queue rỗng. |
| boundaries.json | 44 quan sát,14 verified=true, có complete marker. CLI nguồn TimeEntry đã có dùng giờ thật3 giây; HTTP snapshots đọc actual0.05; running không tính actual và TaskWorkflow chặn cancel; ended rồi cancel không mất actual; blocker/create-resolve snapshot cố định; archive/progress/member filters; input sai; thu hồi calendar.view trong project làm snapshot cũ403/ẩn, bản mới không chứa planned rồi restore. |
| final-readback.json | 19 quan sát,8 verified=true, có complete marker. Browser snapshot/run thực tồn tại; list filters/native navigation; Member bị cấm tạo AI; requester/scope/CSRF; JSON snapshot gốc vẫn byte-identical; entry ended; role/task state đã khôi phục; FK. |
| technical-checks.txt, routes.json | 170 PHP source và118 compiled Blade lint; Pint formatting; Composer validate; view/route/config cache;113 routes; git diff check và FK đều qua. Queue riêng rỗng, không run running. Lệnh recover-stale chạy0 (không phải kiểm thử hard crash). |
| HTTP/walkthrough scripts | Procedural thao tác route thật, đọc lại DB mode=ro. Không là endpoint application hoặc test class. Cookie/state trong storage/app/private, không vào Git/docs. |

Không cộng file before-* trùng bản để tăng số ca, không xóa thất bại helper rồi báo mọi lượt đều xanh. Script đầy đủ đã sửa cách parse CSV; resume-report-queue.py phụ thuộc checkpoint cũ. Các snapshot/dữ liệu kiểm tra đều được giữ để đối chiếu.

## Các điều đã xác minh

- WORK leaf được tính, GROUP/MILESTONE không cộng trùng; CANCELLED không cộng estimate/progress/planned, actual đã ghi vẫn giữ; unknown estimate làm progress fallback count.
- Kỳ local chuyển UTC, [start,end) loại trừ ngày cuối, slot qua nửa đêm bị cắt đúng30 phút khi chọn riêng ngày20/10.
- Snapshot ban đầu estimate180; đổi task bằng form thật thành tổng240: JSON cũ/CSV cũ không đổi, snapshot mới phản ánh240.
- Private TASK_BLOCK của worker không được dùng tính planned cho Owner, dù Owner có quyền quản lý workspace. Thu hồi calendar.view hiện tại chặn xem report cũ có nguồn calendar.
- CSV UTF8/BOM/quote chuẩn; tên user demo bắt đầu `=P6 Worker` được thêm apostrophe khi export. HTML vẫn escaped.
- Web POST tạo AiRun queued và1 job, chưa có AiCall đồng bộ; gửi lại cùng token/nội dung không tạo job/run mới; đổi nội dung trên token cũ báo lỗi.
- Worker thật chuyển queued → running → failed do AI đang tắt; timestamps/call audit thật, response/tokens null. Queue log DONE là job đã xử lý lỗi/ack, không phải AI succeeded. Không có provider call ra ngoài hay kết quả AI mô phỏng.
- User retry qua form và worker3 lượt, lần4 không enqueue. Cancel queued rồi worker không tạo call. Thu hồi quyền project trong lúc chờ khiến worker fail trước adapter, context không mở được khi mất quyền.
- Dashboard/report/AI scope: auth/CSRF, project khác workspace404, actor khác403, Member thiếu report.create403, hidden project không lộ trong tổng hợp. Danh sách filter trước phân trang theo quyền hiện tại.

## Nguồn actual: giới hạn rõ ràng

Chưa có timer UI và không thêm timer endpoint chỉ để làm bài kiểm tra. `elapsed-session.php` bootstrap Laravel, dùng TimeEntry model/observer hiện có, kiểm tra TaskPolicy execute rồi started_at=now(); sau các request HTTP, ended_at=now(). SQL đọc lại cho3 giây thực (0.05 phút). Không dùng fixture lịch sử tưởng tượng hay thay timestamp/đồng hồ để ép giá trị.

Đây là **chuẩn bị nguồn qua model/CLI**, không tuyên bố timer đã có browser end-to-end. Phần report được xác minh thật qua POST/GET và SQLite. Entry đã kết thúc, note ghi rõ nguồn kiểm tra; task được restore TODO, blocker resolved, membership/role active như trước, giữ snapshot khi task canceled/archived để chứng minh không đổi số liệu.

## Flow browser

1. Đăng nhập demo1 bằng form hiện có, mở Dashboard project chọn kỳ01/10–01/11.
2. Bấm “Lưu bản báo cáo” → trang snapshot UUID01a14a03-251a-718b-817b-d1fbc42ea07a, SQL đúng estimate240, planned120, actual null tại thời điểm trước phiên3 giây.
3. Mở form nhận xét AI → nhập prompt P6 Browser → Lưu → queued, chưa có lượt gọi. Chạy worker thật, reload → failed với thông báo AI đang tắt và lượt1 failed, không có text/tokens giả.
4. Mở bản chụp sau phiên thật UUID01a14a05-969c-72aa-897e-2fb67ab9cb67 → thấy actual0.05 phút và nguồn1 phiên ended. Giữ tab này cho chủ dự án duyệt.

Ảnh: report-browser.png; ai-queued-browser.png; ai-failed-browser.png; report-final.png. Shell Blade chỉ là nền chức năng cho FE tiếp tục thiết kế, bảng có thể cuộn ngang ở viewport nhỏ.

## Dữ liệu để duyệt

- Owner local: demo1@example.test / Demo@123456.
- Workspace: 01a149ff-a550-736a-908c-4867444f4481.
- Project chính: 01a149ff-a982-70a4-ad68-56f774575e1d.
- Snapshot gốc estimate180: 01a14a00-032c-7133-8c8d-7a93ce386fff (JSON còn giữ nguyên).
- Snapshot mới estimate240 trước entry: 01a14a01-ad53-7145-b416-d35481fe4d9d.
- Snapshot có actual0.05: 01a14a05-969c-72aa-897e-2fb67ab9cb67.
- AI run3 lượt cấu hình lỗi: 01a14a01-bbb1-7041-8c21-afa67ec251aa.
- AI browser run: 01a14a03-5d93-735d-81ff-bc2d0b138c01 (failed1 lượt, còn retry).
- Entry ended3 giây: 01a14a05-89c0-71cd-938a-9ccad7a45819.
- Các user/role riêng, trạng thái/IDs còn lại nằm state private; không đưa cookies/token form vào báo cáo hoặc Git.

Mở `/workspaces/{workspace}/dashboard?from=2026-10-01&to=2026-11-01&project_id={project}`; snapshot/AI có link trong UI. Các endpoint đều chỉ localhost session MVC, không REST API.

## Vận hành và điều chưa xác minh

Worker thật đã chạy `php artisan queue:work database --queue=work-management-ai --stop-when-empty --sleep=1 --tries=3 --timeout=45`. Helper `pwsh -NoProfile -File scripts/work-ai.ps1 -Drain` đã chạy sau khi queue riêng rỗng và exit0. Không flush queue hoặc consume default jobs của chat khác.

Provider interface/DTO có sẵn, default adapter unconfigured; chưa có provider mạng/API key thực. AI_ENABLED false mặc định. Chưa xác minh successful generation, network timeout, automatic transient backoff, hard kill/crash recovery, concurrent workers/load hoặc MySQL/production. PHP Windows không có pcntl_alarm: không coi timeout45s là hard timeout đã được chứng minh. ai:recover-stale chỉ đã xác minh0 stale; không đổi đồng hồ hay raw SQL để tạo ca giả.

Không chạy lại scripts checkpoint tùy tiện: các task đã DONE/cancel/restore, estimate đã đổi và snapshot đã lưu. http-walkthrough.py tạo workspace/users/dữ liệu mới; đọc nội dung trước khi chạy, còn boundary/final đọc state cụ thể. elapsed-session.php chỉ chuẩn bị nguồn TimeEntry review, không dùng như chức năng vận hành user. Không reset database để ép các script pass.

Snapshot/report ownership + current-source policy, công thức và toàn bộ hợp đồng FE xem STAGE_06_REPORT_QUEUE_AI.md/FRONTEND_HANDOFF.md. Phần6 dừng chờ duyệt, chưa tự sang phần7.

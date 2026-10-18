# Đợt D — P1 quan sát, 18/10/2026

Đã triển khai phần tiếp theo sau P0: Calendar/Timeline, trình bày dashboard/report/CSV, thông báo, trạng thái AI và GitHub. Giữ Laravel MVC/Blade và các service nghiệp vụ hiện có. Không migration mới, không thêm API/SPA hoặc lớp kiểm thử. Đợt E (F04–F10) và P2 vẫn là backlog.

## Thay đổi và flow hàm

### Calendar và Timeline

GET CalendarEventController::index → validate project_id, tìm project trong workspace → CalendarViewService::range dựng [start,end) theo timezone của actor → rows kiểm quyền/phạm vi, query giao với khoảng ngày, giới hạn1000 → presentation dựng điều hướng/ticks → Blade ngày/tuần/tháng/danh sách.

Đã sửa lỗi Calendar index bỏ qua project_id dù link từ project đã gửi tham số này. Chọn project nay chỉ trả sự kiện thuộc project đó và còn calendar.view; không chọn thì đọc tập được phép của workspace. canCreate cũng được kiểm theo project đang chọn. Lịch private chỉ đưa khoảng bận vào projection, không ID, title, description, task hay tên chủ lịch trong HTML. Không dùng CSS để giấu dữ liệu đã gửi xuống.

Thêm view=day (một ngày địa phương), trước/sau/hôm nay giữ project/view/include_cancelled. Phạm vi from/to tùy chọn vẫn tối đa93 ngày, ngày cuối loại trừ; trước/sau dịch theo số ngày của khoảng tùy chọn. Hôm nay xóa range tùy chọn để quay về phạm vi tự nhiên của chế độ. Lưới tháng có tiêu đề thứ/ô đệm đúng ngày; mobile cuộn riêng lưới, có danh sách và chế độ ngày/tuần thay thế. Sự kiện nhiều ngày chỉ thuộc ô ngày có giao thời gian thật; all-day không lặp lại ngày kết thúc.

TimelineController::index dùng range/rows đã kiểm quyền, giữ TASK_BLOCK không-private, tính offset/width từ timestamp UTC cắt tại range, nhóm theo task → Blade trục ngày/slot trên cùng thước và danh sách tương đương. Tick theo nửa đêm của timezone actor; không giả định mọi ngày đều24 giờ. Marker “Hiện tại” là thời điểm tải trang, không phải đồng hồ live. Không kéo/resize hoặc tự suy lịch từ estimate.

Task detail → link Xếp lịch chỉ khi own assigned WORK còn mở, task.update và calendar.create → CalendarEventController::create kiểm task_id/project_id với tập eligible hiện có → prefill title/type/project/task → native POST CalendarService::save kiểm scope/assignment/version/timezone/conflict như cũ. Link chưa ghi DB; không tự tạo lịch từ FE. Task ngoài scope hoặc của người khác không được dùng làm prefill.

### Dashboard, báo cáo và thời lượng

DashboardController → ReportService::metrics (giữ nguyên định nghĩa) → reports/_metrics: KPI WORK mở/quá hạn/blocked/review, bảng trạng thái có bar, workload theo người có meter, planned/actual chung thước. Mọi biểu đồ có số liệu/bảng đọc được, không phụ thuộc màu/tooltip. Không dùng task list phân trang để tự tổng hợp ở client.

Tách “Hiện trạng tại thời điểm tạo” khỏi “Kết quả trong kỳ”. KPI blocked dùng blocked_tasks, không dùng số blocker mở; WORK mở là TODO+IN_PROGRESS+REVIEW. Không biến status hiện tại thành chuỗi lịch sử/burn-down.

MetricDisplay::minutes chỉ đổi **cách hiển thị** sang giờ/phút/giây, làm tròn giây. null → Chưa có dữ liệu; 0 → 0 phút; 0.11 phút → khoảng7 giây, không thành0.11 giờ. JSON/CSV vẫn giữ phút và giá trị nguồn, không ghi ngược thời lượng đã làm tròn.

ReportService::snapshot giữ metrics_json cố định. show/print dùng _snapshot để nêu người tạo, thời điểm, kỳ, timezone, project/người/bộ lọc archive. Link Dashboard/tạo bản mới thay cho sửa số liệu cũ. Export vẫn qua ReportController::bound/Gate rồi đọc snapshot, không tính lại metrics live. Người khác không được đọc snapshot của chủ tạo; quyền nguồn vẫn được kiểm ở policy hiện có.

### Thông báo, AI và tích hợp

NotificationController::index nhận filter=all|unread|read và type trong whitelist task.created/task.updated/task.status_changed/task.comment_added. Áp dụng điều kiện trên NotificationService::visible(actor) trước paginate20; không lọc giả trên một trang. Native PATCH read và POST read-all giữ nguyên; sau server xác nhận mới đổi hiển thị, không client optimistic. read-all áp dụng mọi thông báo còn được phép xem, không chỉ các dòng trên trang.

AI dùng partial _configuration chung: tách cờ bật khỏi việc có key/provider/model/HTTPS endpoint. Có cấu hình không đồng nghĩa kết nối được xác minh. Trang run hiển thị operation, số lượt đã dùng/còn lại, queued/running, lỗi, làm mới, cancel/retry theo quyền; nêu retry dùng context cũ. Không tự polling/POST lại request hoặc tạo phần trăm giả. Nội dung vẫn escape. Key không xuất hiện trong HTML.

Flow đã chạy: HTTP request report → AiRunService ghi AiRun+job database → worker queue thật xử lý job mới → ProcessAiRun báo AI đang tắt → failed,1/3 lượt. Native retry → queued → cancel → cancelled → worker nhận job đã hủy không gọi provider, không tạo call/draft mới. Không bật AI hoặc dùng mock để tạo thành công.

GithubIntegrationController::show lấy latestDelivery theo received_at/id trong project đã kiểm integration.view. UI phân biệt global flag/connection/log gần nhất/chưa có log, nêu content type JSON/push, timezone và trạng thái người dùng. Không hiển thị payload/secret, không gửi request tới GitHub trong lượt này. Log cũ không được gọi là bằng chứng network thành công mới.

## Hợp đồng FE cần giữ

| Thành phần | Contract |
|---|---|
| calendar/_range | navigation.previous/next/today/todayDate/ticks/nowPercent; range.mode hỗ trợ day; timezone là user timezone, không âm thầm dùng workspace timezone |
| Calendar index | project nullable; project_id trong GET phải giữ qua đổi chế độ/ngày; from/to là exclusive end; private rows không có owner/ID/task |
| Calendar create | GET task_id/project_id chỉ prefill sau kiểm quyền. Đừng lấy UUID từ URL rồi coi là được phép; POST vẫn là calendar-save/CSRF/version |
| Timeline | offset_percent/width_percent đã clip; canvas cuộn riêng, danh sách tương đương phải còn truy cập được bằng bàn phím |
| reports/_metrics | metrics schema1 không đổi; dùng MetricDisplay cho UI, giữ raw data-minutes/null khi cần kiểm; CSV luôn dùng nguồn phút |
| reports/_snapshot | snapshot.generator, created_at và metrics filters/period; không trộn số liệu live với snapshot |
| notifications/index | filter/type whitelist server, pagination query string, native read/unread forms và data-notification-id |
| ai-runs/_configuration | data-ai-configured=0/1 là readiness cấu hình, không phải provider verified; raw status/expected_status vẫn enum |
| public/css/p1-observation.css | Entry sau p0-ui.css; calendar-grid-scroll/timeline-scroll là vùng cuộn được focus; chart bars trang trí có bảng số liệu |

Các file Blade và route hiện có đã nối logic/database; FE tiếp tục bố cục/màu/chữ mà giữ CSRF, expected_status, version và private projection. Cờ AI/integration, APP_KEY và schema DB chính không đổi. Source ZIP được đóng lại, không kèm dữ liệu/key/cookies.

## Minh chứng và giới hạn

Xem verification/p1-observation/README.md: HTTP thật/session/CSRF/form, SQL read-back, ảnh IAB319px và worker. Đã kiểm90 phút planned/null actual, CSV phút, snapshot không đổi khi sửa task/thêm lịch mới; actual từ timer bắt đầu/chờ/dừng thật. Slot nguồn60 phút qua nửa đêm được clip30 phút ở ngày kế; all-day chỉ hiện ngày bắt đầu; canceled chỉ hiện khi chọn.

Server8123 đã dừng trước lượt này và được khởi động lại cục bộ, không đổi environment/key. Một kiểm tra helper CSV ban đầu so chuỗi thô nên không nhận trường có quote; đã sửa dùng CSV parser, giữ log false và bước chạy tiếp. Không sửa app để né kiểm tra.

Chưa xác minh provider AI/network GitHub, MariaDB/MySQL, màn hình desktop rộng, screen reader chuyên dụng hoặc load test lớn. Lượt này không thay schema/dependency nên không lặp lại cài mới đã kiểm ở P0. Không thêm sửa time-entry, bulk action, reminder, saved filter hoặc custom permission. Các phần đó thuộc đợt E/P2, không được ghi là đã làm.

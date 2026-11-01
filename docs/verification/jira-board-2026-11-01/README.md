# Kanban Jira — bàn giao ngày 01/11/2026

Đã bổ sung giao diện và hành vi Kanban trên Laravel MVC/Blade, dùng route workflow hiện có. Xác minh HTTP/controller/service/SQLite thật đã hoàn tất; **chưa xác minh tương tác và hình ảnh trong trình duyệt** vì công cụ không được phép dùng Chrome trong phiên này. Không chạy test suite, không tạo TestCase, không mock database hay endpoint.

## File và flow

- `resources/views/components/layout.blade.php`: thêm `@stack('styles')` sau các stylesheet và `@stack('scripts')` trước đóng body.
- `resources/views/tasks/board.blade.php`: push asset có version theo filemtime; năm chip, tìm nhanh, toggle bộ lọc server, năm cột trạng thái, badge tổng, thẻ có data attributes, avatar, deadline, ưu tiên, phân trang và liên kết panel.
- `public/css/jira-board.css`: CSS riêng cho bảng, token theme sáng/tối, màu từng trạng thái, border drop zone, hover, focus, cuộn ngang trên màn hình nhỏ và reduced motion; toast góc phải dưới.
- `public/js/jira-board.js`: HTML5 drag/drop, lọc theo title/code (hỗ trợ nhập không dấu), người thực hiện, high/urgent, overdue và unassigned. Một chip được chọn tại một thời điểm; search kết hợp chip. Lọc client không gửi request.
- `TaskController::index()`: chỉ nhánh board thêm `withCount(children)` và số children DONE để không truy vấn count riêng trong Blade. Không thay đổi nhánh danh sách, route hay service workflow.

Kéo thả → di chuyển tạm thẻ → PATCH URL Blade cấp qua `data-status-url`, JSON `{expected_status, status}`, CSRF/Accept/Content-Type → `TaskActionRequest` → `TaskWorkflowController::status()` → `TaskWorkflowService::status()` kiểm tra quyền, workflow, blocker, dependency, timer và trạng thái cũ → ghi DB/history. Chỉ sau response thành công mới cập nhật trạng thái/bộ đếm và báo lưu thành công.

Sau PATCH thành công, GET lại URL bảng hiện tại và thay phần board bằng Blade mới để đồng bộ quyền, filter, phân trang; giữ tìm kiếm, chip và cuộn ngang. `WorkUI.mount()` gắn lại tiện ích form. Khi panel chung thay nội dung main, MutationObserver gắn lại hành vi board, không đăng ký handler trùng trên cùng phần tử.

Khi server từ chối, thẻ trở về đúng vị trí cũ và hiển thị lỗi. Nếu mạng/timeout/response không xác định, thông báo không khẳng định DB đã rollback; tạm khóa kéo lại thẻ và đề nghị tải bảng. Nếu PATCH đã thành công nhưng GET làm mới thất bại, không hoàn tác giao diện như thể ghi DB thất bại; yêu cầu tải lại để đồng bộ.

Giữ `can('update')` cho Sửa, `can('execute') || can('review')` cho kéo thả và Đổi trạng thái. Server luôn kiểm tra lại. Hủy/mở lại/yêu cầu sửa cần lý do: drop với payload hai trường sẽ bị server từ chối, thông báo hướng dẫn dùng nút Đổi trạng thái. Nút này cũng là cách thao tác bằng bàn phím/điện thoại và khi tắt JavaScript.

## Phạm vi dữ liệu

Board vẫn phân trang 10 thẻ/cột. Chip và search chỉ lọc thẻ **đã tải**, có chú thích ngay trên bảng. Khi lọc nhanh, badge hiển thị số thẻ phù hợp trên trang / tổng cột theo filter server. Bộ lọc nâng cao dùng nguyên partial/form GET hiện có để tìm toàn bộ dữ liệu trong quyền truy cập.

Deadline sắp đến hạn là trong 24 giờ tiếp theo; DONE/CANCELLED không đánh dấu quá hạn. Avatar dùng tên thật từ quan hệ assignee, không tự tạo người phụ trách.

Badge subtasks được render khi `children_count > 0`. Domain hiện chỉ cho GROUP làm cha, trong khi board hiện loại GROUP; vì vậy dữ liệu hợp lệ hiện tại thường không có badge này. Không tạo quan hệ con trái quy tắc chỉ để minh họa badge.

## Minh chứng thực hiện

Database riêng: `database/jira_board_review_20261101.sqlite` (gitignored). Server review dùng port 8137, SESSION_COOKIE riêng, không đổi `.env`/APP_KEY. Migrate thông thường trên database mới, seed `DemoSeeder`. Fixture tạo thêm qua route thật có nhãn `[Review Jira]`, không phải dữ liệu người dùng; giữ tích hợp ngoài không chạy.

| Kiểm tra trực tiếp | Kết quả |
|---|---|
| Tạo bốn fixture qua HTTP | 201, đọc lại SQLite có đúng code/title/priority/assignee/deadline |
| Render board | 200, đủ năm cột/năm chip/data attributes, CSS trong head, JS cuối body, title được escape |
| TODO → IN_PROGRESS → REVIEW → DONE | 200 mỗi bước, DB đúng trạng thái và revision tăng; ba HISTORY `task.status_changed` |
| Gửi expected_status cũ | 422, DB vẫn DONE |
| Mở lại không có lý do | 422 trường reason, DB vẫn DONE |
| Tạo blocker rồi thử bắt đầu | 422, task vẫn TODO |
| Viewer xem board / PATCH | Không có thẻ draggable hay action Sửa/Đổi trạng thái trong card; PATCH 403 |
| CSRF sai | 419, không đổi trạng thái |
| Search server / filter DONE / trang TODO thứ hai | 200, kết quả và phân trang đúng |
| Danh sách, workspace, board cá nhân, panel chi tiết/trạng thái | 200; danh sách/workspace không nạp asset Kanban |
| Deadline sau đọc lại | Task DONE hết đánh dấu quá hạn; BLOCK còn quá hạn; SOON có badge sắp hạn; FREE chưa phân công |
| Đọc database | Ba history chuyển trạng thái; `PRAGMA foreign_key_check` rỗng |
| Kiểm tra mã | PHP lint đạt; git diff --check không báo whitespace lỗi; Node parse được JS tới chỗ cần browser `document` |

- [http-flow.json](http-flow.json): request chuyển trạng thái, HTTP response, snapshot DB trước/sau, lỗi bị từ chối và URL review.
- [readback.json](readback.json): lần đọc lại cuối, deadline flags, history event/data, FK và SHA-256 của file nguồn tại thời điểm xác minh.
- [walkthrough.py](walkthrough.py): thao tác HTTP có thể chạy lại trên server/database review riêng; SQLite chỉ đọc. Mỗi lần chạy tạo fixture với mã timestamp mới, không reset dữ liệu.

Lần thử walkthrough đầu tiên dừng vì script mong HTTP 200 khi tạo task, trong khi route trả đúng 201. Đã sửa kỳ vọng rồi chạy toàn bộ thành công; fixture lần đầu vẫn được giữ. Đây là lỗi kỳ vọng của script, không phải lỗi tạo task. Lần đầu đọc history dùng cột content (null với HISTORY); `readback.json` đọc đúng event_type/data.

## Giới hạn và thao tác còn cần kiểm tra bằng trình duyệt

Browser host trả `The browser access host is unavailable`; khi chuyển sang native Chrome, công cụ trả `Computer Use was not approved to use Google Chrome`. Không tự vượt hạn chế đó. Vì vậy chưa có ảnh desktop/mobile, chưa xác nhận DOM drag/drop, toast, rollback vị trí, filter tức thì hoặc remount sau panel bằng thao tác UI. HTTP thành công và JS parse không thay cho các kiểm tra này.

Khi có trình duyệt: đăng nhập demo trên server review → mở URL `board_url` trong http-flow → kiểm tra từng chip/search, toggle server filter, kéo một task TODO không blocker sang IN_PROGRESS, kéo task BLOCK để thấy rollback, dùng panel đổi trạng thái cần lý do, lưu/sửa task qua panel rồi thử lại filter/drag. Kiểm tra thêm theme tối, viewport hẹp, Tab/Enter, offline và phiên khác cập nhật cùng task. Dùng nút Đổi trạng thái trên thiết bị cảm ứng.

Trong lúc triển khai, workspace có chỉnh sửa đồng thời ở `modern-theme.css`, `tasks/panel.blade.php`, nút tạo nhanh trong layout, và một số dòng board/JS. Đã giữ các phần ngoài phạm vi; đối chiếu và bổ sung xử lý lỗi/sync cần thiết trên bản Kanban hiện hành. Không sửa/xóa báo cáo cũ, không tạo commit.

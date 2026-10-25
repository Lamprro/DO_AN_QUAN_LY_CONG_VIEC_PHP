# Cải tiến sử dụng và bảo trì — 25/10/2026

## Kết quả

Giữ Laravel MVC/Blade. Tạo, xem, sửa và chuyển trạng thái công việc ngay trên danh sách/Kanban; liên kết trong cây và tổng quan dự án dùng cùng cửa sổ. Backend vẫn quyết định quyền, validation, revision và workflow. Không thêm lớp test, không đổi thành SPA/API riêng.

### Trải nghiệm mới

- Bấm tên để xem nhanh; bấm Sửa để chỉnh tên, mô tả, ưu tiên, ước lượng, hạn, người làm/người duyệt theo quyền. Có đường dẫn mở trang đầy đủ cho các thao tác chuyên sâu.
- Một cửa sổ dùng chung thay cho hai modal và các bộ xử lý JavaScript trùng nhau. Dữ liệu sửa được đọc mới khi mở, không nhúng toàn bộ đối tượng vào mỗi hàng.
- Nút Lưu luôn có thể tiếp cận ở cuối vùng nhìn; focus, Escape, cảnh báo bản nháp, Ctrl+Enter; giao diện một cột trên điện thoại. Gợi ý thời gian theo múi giờ workspace do PHP tính.
- Lưu thành công tải lại nội dung trang hiện tại từ Blade: số lượng, bộ lọc, cột Kanban và revision được server tính lại. Giữ URL, vị trí cuộn và lựa chọn hàng loạt còn hiển thị. Nếu trang nền có bản nhập chưa lưu, không ghi đè bản nhập đó.
- Validation giữ form; phiên hết hạn/quyền bị thu hồi/lỗi kết nối có thông báo. Nếu đã lưu nhưng không tải lại được nền, nói rõ đã lưu và yêu cầu tải lại; không gửi lặp âm thầm.
- Sửa đồng thời mở bảng đối chiếu trong chính cửa sổ: chọn giá trị từng trường, kiểm tra lại quyền và revision khi ghi.
- Nút trạng thái lấy từ `TaskWorkflowService::availability()`, có lý do khi blocker/dependency/timer ngăn hành động. Không cho chọn tùy ý toàn bộ trạng thái.

### Lỗi đã sửa

1. Tổng quan dự án trả 500 vì thiếu `$parent`/`$children`: bổ sung truy vấn theo phạm vi được xem.
2. XAMPP/MariaDB 10.4 tự gắn `ON UPDATE CURRENT_TIMESTAMP` vào cột TIMESTAMP bắt buộc đầu tiên: chạy timer thật phát hiện giờ bắt đầu bị ghi lại khi dừng. Bốn cột bị ảnh hưởng: workspace_members.joined_at, project_members.joined_at, time_entries.started_at, ai_calls.started_at. Migration mới đặt default rõ ràng, bỏ tự đổi khi UPDATE; không chuyển kiểu, không đổi giá trị hiện có. Đã áp dụng trên database kiểm tra và database chính, sao lưu riêng và đối chiếu bốn bảng trước/sau.
3. Trong quá trình gộp lớp, flow dữ liệu phát hiện chỗ gọi lớp cũ ở trang phiên giờ và các lời gọi ghi lịch sử. Đã đổi toàn bộ caller và chạy lại timer, chỉnh giờ, watcher, upload/download.

### Tổ chức PHP

| Bỏ lớp chỉ chuyển tiếp | Nơi xử lý trực tiếp |
|---|---|
| TaskCollaborationService | TaskUtilityService: people, viewData, mentions, watch |
| TaskAttachmentService | TaskUtilityService: storeAttachment, downloadAttachment, removeAttachment |
| WorkHoursService | TimeTrackingService: workHoursValues, workHoursVersion, saveWorkHours |
| TimeAdjustmentService | TimeTrackingService: allowed, version, saveAdjustment |
| TaskEditData | TaskService: presets, presetsForDate |
| DeadlineInsight | TaskService: deadlineInsight |

Giữ TaskHistory với triển khai thật `record()`, vì nhiều luồng cùng ghi lịch sử/thông báo. Giữ các lớp phân quyền, cây, workflow, kế hoạch có trách nhiệm nghiệp vụ cụ thể. Đã loại truy vấn lấy thành viên trùng ở danh sách và hai truy vấn không còn dùng ở tổng quan dự án. Không gộp mọi nghiệp vụ vào controller.

## Luồng qua hàm

1. Link `data-task-panel` → `task-panel.js/open()` → GET route create/edit/show với `panel=1` → TaskController → `tasks/panel.blade.php`.
2. Submit → route web hiện có + CSRF/session → TaskRequest/Policy → `TaskController::saveEdit()` → `TaskService::save()` → transaction/revision → `TaskHistory::record()` → NotificationService.
3. Thành công → `refreshPage()` nhận HTML mới → thay nội dung main → `WorkUI.mount()` gắn lại thao tác → đóng dialog và báo kết quả.
4. Xung đột → TaskEditConflict → fragment `tasks/conflict` → `resolveEdit()` → kiểm tra lại trước khi lưu lựa chọn.
5. Trạng thái → `statusForm()` → `availability()`; submit → `TaskWorkflowController::status()` → `TaskWorkflowService::status()` → kiểm tra quyền/blocker/dependency/timer → ghi lịch sử và đồng bộ nhóm cha.
6. Giờ làm/timer → TimeTrackingController/TimeAdjustmentController/WorkHoursController → TimeTrackingService; attachment/watcher → controller hiện có → TaskUtilityService. File vẫn lưu private và tải qua route có quyền.

## Kiểm chứng và dữ liệu

- Môi trường kiểm tra riêng dùng MariaDB thật của XAMPP; HTTP session/CSRF/route thật, không gọi service giả hoặc ghi SQL để làm cho flow thành công.
- 31 URL chính mở thành công; flow tạo/sửa/validation/revision/xung đột/múi giờ/blocker/quyền Viewer/cross-scope/escaping được ghi trong `verification/ux25/flow-panels.json`.
- Timer start/stop giữ nguyên started_at; chỉnh phiên giờ lưu audit; Viewer không sửa được; work-hours version chặn stale; watcher và history tồn tại; file upload/download đúng byte: `flow-services.json`.
- Trình duyệt thật xác nhận danh sách, tạo tại chỗ, Kanban, đối chiếu hai phiên, bản nháp, bulk revision, mobile và theme: `browser-review.json`, ảnh desktop/mobile.
- PHP syntax/Pint, JavaScript syntax, route cache, Blade compile, preflight, Composer validate/audit, Git whitespace được ghi riêng. Không lấy việc compile thành công thay cho minh chứng nghiệp vụ.
- `.env` chính giữ nguyên; các bảng nghiệp vụ chính được so hash toàn bộ trước/sau. Chỉ bảng migrations tăng một bản ghi. Không seed hay kiểm tra thao tác nghiệp vụ trên dữ liệu chính. Backup riêng nằm ngoài Git.

## Giới hạn cần biết trước production

- Đây là đợt cải tiến giao diện công việc và hồi quy các chức năng liên quan; không khẳng định mọi tình huống/tích hợp ngoài hệ thống đã được kiểm tra toàn bộ trong lượt này.
- Email, Gemini/AI và đồng bộ dịch vụ ngoài không được gọi bằng key thật trong đợt kiểm tra này. Cần kiểm chứng trên môi trường triển khai với cấu hình thực tế, worker và scheduler.
- Migration ngăn timestamp sai về sau; không đoán lại thời điểm cũ nếu từng bị MariaDB ghi đè. Database chính chưa có phiên time_entries tại thời điểm chụp baseline. Với joined_at/ai_calls lịch sử, cần đối chiếu nguồn hoặc backup nếu có yêu cầu kiểm toán thời điểm.
- Trên máy khác chạy migration mới khi triển khai. `down()` chủ ý giữ default an toàn, không phục hồi hành vi ON UPDATE gây sai dữ liệu.
- Cửa sổ nhanh tập trung thao tác thường dùng. Bình luận đầy đủ, tệp, dependency và các thiết lập nâng cao vẫn có trang chi tiết; không che giấu chức năng hiện hữu.

## Tham khảo flow

- [ClickUp Task layouts](https://help.clickup.com/hc/en-us/articles/29665520762647-Task-layouts): modal/sidebar giữ ngữ cảnh danh sách.
- [Asana navigation](https://help.asana.com/s/article/navigating-asana?language=en_US): vùng chi tiết công việc và hành động theo ngữ cảnh.
- [MariaDB TIMESTAMP](https://mariadb.com/docs/server/reference/data-types/date-and-time-data-types/timestamp): hành vi cột TIMESTAMP đầu tiên khi explicit_defaults_for_timestamp tắt.

Áp dụng các nguyên tắc tương tác vào MVC hiện có; không sao chép toàn bộ giao diện sản phẩm khác.

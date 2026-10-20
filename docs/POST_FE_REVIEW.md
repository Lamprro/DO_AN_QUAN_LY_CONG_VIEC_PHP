# Rà soát và hoàn thiện sau bàn giao FE — 20/10/2026

Giữ Laravel MVC/Blade, session/CSRF, Eloquent/service/policy. Tiếp nhận55 file FE đang thay đổi trên nền commit7f0867d, giữ thiết kế mới và sửa tích hợp. Không reset nguồn của AI FE, không sửa commit hoặc xóa báo cáo vòng trước. Patch FE đầu vào và bản sao database chính được giữ trong private runtime; không đưa dữ liệu/khóa vào source.

## Đánh giá từ bằng chứng và thay đổi

| Mức | Vấn đề quan sát | Xử lý |
|---|---|---|
| P0 | Preview có nhánh trả valid=true khi payload thiếu/rỗng; fixture trông như có thể áp dụng dù apply từ chối | PlanService::payload kiểm cấu trúc/schema chuẩn; preview và propose/approve/apply/local schedule/copy bản dùng chung; dữ liệu cũ hiện thông báo, không dựng thành công giả. Preview hợp lệ vẫn đi đường apply thật rồi rollback. |
| P0 | Calendar form sau lỗi tự lấy version mới trong khi giữ trường cũ | Hiddenversion dùng old input. Stale từ hai session từ chối, giữ version và nội dung; gửi lại trực tiếp từ form lỗi vẫn bị chặn. |
| P0 | URL `?fresh=1` còn trên form sau khi nạp dữ liệu; validation redirect quay lại URL đó và xóa old input | Task/Project/Calendar/TimeAdjustment edit tiêu thụ fresh rồi302 về URL không có fresh. Sau đó form lỗi giữ dữ liệu và version; nạp lại là một thao tác rõ ràng. |
| P1 | Grid inline min-width360px tràn vùng nội dung trên viewport390px; CSS filter-bar cũ làm grid bộ lọc mới chỉ còn một cột nhỏ trên desktop | Minmax thích ứng theo100%, sửa container grid/filter, bọc overflow đúng vùng, focus rõ, reduced-motion/print; đo lại settings390px không còn vùng tràn. Kanban/timeline có cuộn ngang cục bộ có chủ ý. |
| P1 | Theme localStorage một khóa chung có thể áp màu của tài khoản trước và lấn cấu hình đã lưu; theo thiết bị xử lý chưa đầy đủ | theme.js chạy trong head, key theo UUID/guest, đối chiếu server preference, hỗ trợ system, storage event và lưu cấu hình từ form. Không lưu theme tạm vào DB qua nút header. |
| P1 | Bulk không cho biết số dòng, hiển thị cả trường phân công khi đổi priority; dễ nhầm chọn toàn bộ trang khác | Bộ đếm, checkbox chọn các dòng đủ điều kiện trong trang, trạng thái indeterminate; chỉ gửi trường đúng action. Không có dòng thì chặn tại client, server vẫn quyết định quyền/version/all-or-nothing. Giữ hộp xác nhận native hiện có. |
| P1 | Pagination mặc định có nhãn tiếng Anh và hai bộ markup phụ thuộc Tailwind nhưng không nạp framework đó | View pagination.native/simple dùng CSS riêng và nhãn tiếng Việt, server URL thật, không nhân đôi nút theo breakpoint. |
| Hoàn thiện | Người dùng phải mở từng project mới tìm việc; chưa có view theo cột trạng thái | Thêm SearchController và Kanban Blade theo policy/phân trang thật. F15 tìm kiếm xuyên project được triển khai trong vòng này; Kanban là cách xem và mở workflow hiện có, không tự đổi status bằng kéo thả. |
| Trình bày | Một số câu FE mô tả người tham gia lịch như đã gửi thông báo; hướng dẫn có class/permission/key không tồn tại | Nhãn participant nói đúng là tham khảo, không đặt lịch/gửi tin cho họ. Hợp đồng thật ở phần dưới và FRONTEND_HANDOFF. Demo credentials chỉ xuất ở local; loại bỏ phụ thuộc tải Google Fonts để dựng giao diện cơ bản. |

Không dùng câu “HTTP200” để kết luận100% chức năng hoặc khả năng đưa lên production. Chưa chấm điểm UX bằng nghiên cứu người dùng; nhận xét dựa trên source, giao diện và dữ liệu flow thật.

## Chức năng hiện tại và flow

Auth/profile → workspace/role/invitation/member → project/project member → task/list/tree/board → blocker/FS/review/timer → calendar/timeline → plan/version/preview/apply → report/snapshot/CSV/print → notification/reminder/operations đã có route/service/Blade. AI yêu cầu qua database queue, khi tắt provider phải thất bại thật, không sinh báo cáo giả. Tệp lưu riêng, tải qua quyền nguồn hiện tại.

### Tìm kiếm xuyên dự án (F15)

GET `/search` → SearchController::index → workspace hiện còn membership/quyền → project visible và task.view → query task/project → phân trang độc lập → search/index. Tham số q≤200, workspace_id tùy chọn, type=all|tasks|projects. Tasks_page/projects_page độc lập. Chỉ tìm khi q không rỗng; loại bản ghi lưu trữ; `%` và `_` là ký tự tìm literal, không biến thành wildcard quét mọi dữ liệu. Phạm vi ngoài quyền403; không xuất tên/snippet/count của nguồn không được xem. Task và project đang bị thu hồi quyền biến khỏi lần query tiếp theo, không dựa vào quyền đã lưu của bộ lọc.

### Kanban

TaskController::index + TaskFilters với view=board → cùng query scope/tìm/lọc/sort → năm paginator theo status,10 việc/cột → tasks/board. page_todo/page_in_progress/page_review/page_done/page_cancelled độc lập. GROUP xem bằng cây; card mở trang chi tiết để dùng TaskWorkflowService có reviewer, blocker, dependency/lag, timer và expected_status. Không thay review hoặc DONE bằng thay đổi DOM. Bộ lọc lưu riêng có thể giữ view=board; chuyển về list giữ bộ lọc. per_page của list không nâng giới hạn10/cột trên board.

### Kế hoạch và dữ liệu cũ

PlanService::payload từ chối thiếu payload chuẩn; validate require schema1 và ít nhất1 task. PlanReviewService::preview kiểm cùng dữ liệu rồi chạy transition apply trong transaction cuối cùng rollback. PlanController::create với base cũ thiếu chuẩn chuyển về show kèm cảnh báo; PlanScheduleService::suggest từ chối rõ thay vì lỗi truy cập mảng. Diff dữ liệu cũ chỉ để đọc, không là bằng chứng đủ điều kiện áp dụng. Không tự sửa/migrate fixture hoặc lịch sử đang có.

### Form dữ liệu cũ

Edit `?fresh=1` → xóa `_old_input` một lần → redirect URL chuẩn → hiển thị current revision/version. Submit → validator/service so revision/version → khi lỗi redirect URL chuẩn với old input. Không tự đổi token sang bản mới rồi ghi đè. Calendar version là hash; task/project là edit revision; time entry hash thêm số audit.

## Kiểm chứng vòng này

[Hồ sơ flow thật](verification/post-fe/README.md) có main-pages.json, flow-core/calendar-plan/reports-queue/admin.json, release-install.json, technical.json. Bản cài review sử dụng source hiện tại và database SQLite riêng, seed2lần; vendor dùng lại trên cùng máy. Kiểm qua HTTP có cookie/CSRF/form thật, đọc SQL, worker thực và quan sát browser. Không thêm TestCase/PHPUnit/mocks hoặc reset DB chính.

Đã xác nhận: tìm kiếm không lộ workspace/project khác; quyền vừa thu hồi làm mất nguồn trong tìm kiếm và worker bỏ nhắc; board10+2; bulk hai dòng và rollback stale; blocker/FS/timer/review; comment escape; private busy không lộ title/id/link; multipart upload/download đúng bytes; role CRUD, lời mời nhận bằng file-cache local, thêm/gỡ project member; plan draft-preview-propose-approve-apply và idempotency; lỗi kế hoạch rỗng; snapshot bất biến, actual mới theo correction có audit; CSV/print; queue AI disabled failed; reminder sent/dedupe; settings lưu thật.

Hộp JSconfirm cũ làm công cụ IAB bị kẹt trong lần bấm bulk cuối. Đã quan sát chọn2 dòng/counter/revision/action trước bước xác nhận, nhưng không ghi lần đó là browser bulk thành công. Bulk đã kiểm thành công qua HTTP thật và SQL; không đổi app để tự bỏ xác nhận. Bản thử dialog khác đã bỏ, giữ cơ chế native hiện có. Một số tương tác client như toggle theme/cancel dialog chưa được xác nhận đầy đủ sau khi công cụ bị kẹt; syntax và server contract đã kiểm, giới hạn ghi tại hồ sơ thay vì báo100% button pass.

## Vận hành và giới hạn

Không có migration mới ở vòng này. Giữ .env/APP_KEY/cấu hình provider và dữ liệu chính; đối chiếu hash các bảng tasks/projects/plans/calendar/time/history/membership trước/sau không thay đổi. Mọi thao tác ghi của nghiệm thu nằm ở database review. Không áp dụng scheduler/worker như service hệ điều hành tự khởi động; tài liệu chạy riêng vẫn tại PHASE_E_UTILITIES/DEPLOYMENT.

AI/provider, SMTP thật, Redis production cho invitation, GitHub public HTTPS/secret vẫn chờ cấu hình; chưa nghiệm thu các kết nối này thành công. Tên key thật `AI_API_KEY`, queue `work-management-ai`, job `ProcessAiRun`; reminder queue `work-management-reminders`. Calendar hiện quản lý lịch chính người dùng bằng CalendarEventController/CalendarService và quyền calendar.create/update/delete; không có permission calendar.manage hoặc API reschedule mới. Bulk TaskUtilityController/TaskUtilityService giới hạn50 đồng bộ nguyên tử, không có TaskBulkController/Job giả. Timer TimeTrackingController; preview PlanReviewService.

Mở rộng còn tách riêng: F11 đặt lịch thay người khác/consent, F12 giờ làm tùy chỉnh, F13 watcher/mention/nhiều người làm, F14 recurring/template và drag-drop calendar/board. Những đề xuất này cần thêm quy tắc và flow riêng; không gán là đã có chỉ vì giao diện hiển thị. Chưa đo tải lớn/MySQL/kill-worker giữa transaction hoặc audit accessibility toàn diện. Các core flow local phía trên có bằng chứng thật trong vòng này.

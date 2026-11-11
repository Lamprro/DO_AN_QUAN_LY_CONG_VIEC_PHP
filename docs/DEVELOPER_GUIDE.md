# Bắt đầu đọc và sửa dự án

Dự án dùng Laravel MVC + Blade. Hãy bắt đầu từ tên route trong form/link, tìm ở `routes/web.php`, `routes/auth.php` hoặc `routes/workspace/`, rồi đọc method controller và service tương ứng. Không cần đi qua một Query riêng chỉ để xem vài dòng dữ liệu của cùng nghiệp vụ.

## Bản đồ điểm vào

| Nhóm | Controller | Nơi xử lý và lý do giữ các lớp phụ |
|---|---|---|
| Đăng nhập, đăng ký, xác minh, reset | `Auth/*Controller` | `AccountService`; `AccountMailService` tách gửi mail. Các controller Auth tương ứng các vòng đời bảo mật khác nhau. |
| Hồ sơ, cấu hình cá nhân | `ProfileController`, `SettingController` | `AccountService`, `SettingService`. Settings là JSON dùng chung với thông báo và giờ làm; không kéo nghiệp vụ đăng ký vào từng lần đọc cấu hình. |
| Workspace, thành viên, vai trò | `WorkspaceController` | `WorkspaceService`: create/update/transfer/delete, member/role và dữ liệu màn hình. |
| Lời mời | `InvitationController` | `InvitationService`: token cache, thời hạn, gửi mail, kiểm lại người mời khi chấp nhận. |
| Project và thành viên | `ProjectController` | `ProjectService`: CRUD, archive/restore, member và màn hình. |
| Task, lọc, bulk | `TaskController` | `TaskService`; `TaskPageData` chuẩn bị các màn hình, `TaskListingQuery` chia sẻ phạm vi tổng hợp cá nhân. |
| Trạng thái, dependency, blocker, bình luận, watcher, tệp | `TaskWorkflowController` | `TaskWorkflowService`; `TaskParticipants` chọn người hợp lệ, `TaskHistory` ghi audit/thông báo. Dependency là quan hệ FS; blocker là trở ngại được báo và giải quyết. |
| Cây task | task controllers | `TaskTreeService` giữ thuật toán cây và tổng hợp trạng thái; không tải sibling ngoài phạm vi cá nhân. |
| Xung đột sửa | `TaskController.resolveEdit` | `TaskEditConflict` quản lý bản đối chiếu trong session; `TaskService.save` vẫn kiểm revision dưới khóa. |
| Mẫu/lặp | `TaskTemplateController` | `TaskTemplateService` chứa cả màn hình và vòng đời mẫu/kỳ chạy có khóa, revision, occurrence key. |
| Timer, điều chỉnh, giờ làm | `TimeTrackingController` | `TimeTrackingService`: một timer đang chạy, không trùng thời gian, audit, version; giờ làm ở `users.settings`. |
| Lịch/timeline/đề xuất | `CalendarController` | `CalendarPageData` chỉ chuẩn bị màn hình; `CalendarService` ghi lịch; `TeamCalendarService` xử lý thỏa thuận giữa hai người. |
| Kế hoạch | `PlanController` | `PlanPageData` cho màn hình/trình soạn; `PlanService` validate/schema/version/duyệt/apply; `PlanReviewService` diff/preview rollback; `PlanScheduleService` xếp lịch local. |
| Báo cáo | `DashboardController`, `ReportController` | `ReportService` tính số liệu, snapshot bất biến, lịch sử, CSV và kiểm quyền nguồn hiện tại. |
| AI | `AiRunController`, `ProcessAiRun` | `AiRunService` vòng đời/retry/idempotency; `PlanningContext` snapshot và kiểm kết quả; provider adapters chỉ gọi giao thức ngoài. AI tạo nháp, không tự apply. |
| Thông báo | `NotificationController` | `NotificationService`: nhận sự kiện, dedupe, quyền xem hiện tại, trạng thái đọc. |
| Nhắc việc/vận hành | `UtilityObservationController`, `SendTaskReminder` | `TaskReminderService` scan/deliver có ledger; `QueueObservation` chỉ chiếu dữ liệu người đang xem được phép. |
| GitHub | `GithubIntegrationController` | `GithubSettingsService` cấu hình bí mật mã hóa; `GithubWebhookService` xác thực request public và ghi delivery/activity. |
| Tìm kiếm | `SearchController` | `SearchService` giới hạn workspace/project/task theo quyền trước khi tìm. |

## Cách thêm chức năng

1. Tìm nhóm nghiệp vụ đang sở hữu dữ liệu; ưu tiên thêm method có tên cụ thể vào controller/service đó.
2. Controller nhận/chuẩn hóa/validate đầu vào, giải quyết model thuộc route và chọn view/redirect/JSON. Các điều kiện nghiệp vụ, transaction và audit ở service.
3. Ghi dữ liệu phải giữ thứ tự khóa của flow, kiểm quyền trên dữ liệu mới đọc, kiểm revision/version trước khi ghi. Không dựa vào việc nút đã hiện trên giao diện.
4. Policy là ability trên model; Access tính quyền/phạm vi dùng chung. Model giữ quan hệ/cast/query scope; observer giữ bất biến cấu trúc. Hook framework `User.sendEmailVerificationNotification` chuyển việc gửi sang service.
5. Chỉ tách lớp khi có trách nhiệm độc lập như thuật toán cây, lập lịch, giao thức nhà cung cấp hoặc chuẩn bị màn hình phức tạp. Không áp dụng máy móc mỗi bảng một lớp hay mỗi thao tác một lớp.
6. Kiểm tra route/controller/service/database thật trên SQLite review riêng; không reset dữ liệu đang dùng. Bằng chứng hiện hành ở `docs/verification/readability-2026-11-12`.

## Ba quy tắc dễ hiểu nhầm

- Công việc cá nhân tổng hợp task trực tiếp và task được giao trong nguồn được phép xem; không chép hoặc chuyển task khỏi project. Mọi thao tác quay lại nguồn gốc và kiểm quyền hiện tại. Bulk từ cá nhân chỉ nhận task trực tiếp trong phạm vi đó.
- Đề xuất lịch và sự kiện cùng bảng `calendar_events`. Scope mặc định ẩn trạng thái chỉ là đề xuất; `proposals()` đọc vòng đời đề xuất. Accept đổi chính bản ghi sang planned.
- `ProjectAccess.resolve` giải quyết ngữ cảnh/quyền project. Xung đột phiên sửa task do `TaskEditConflict.resolve`; hai việc không liên quan.

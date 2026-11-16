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

## Form lớn dùng chung

Layout nạp `public/js/page-editor.js` và `public/css/page-editor.css`. Link tạo/sửa, hồ sơ, cấu hình, giờ làm và chỉnh timer mở một dialog gần toàn màn hình. Mỗi editor tải nguyên route Blade vào iframe cùng origin; các script của trang, CSRF, FormRequest, redirect, version và màn hình conflict vẫn chạy trên HTTP hiện có. `?panel=1` còn được server hỗ trợ để không phá hợp đồng cũ.

Các form đang nằm trong bảng hoặc thẻ (thành viên, lời mời, mẫu, đề xuất lịch, GitHub, xếp lịch, bình luận, blocker, dependency, watcher, tệp, bộ lọc và bulk) dùng `data-form` để mở đúng form trong khung. Thao tác tức thời không có trường nhập như xóa/đọc thông báo/start-stop timer vẫn theo nút hiện hành. Form đổi trạng thái giữ các nút chuyển trạng thái riêng, không tự chọn một trạng thái cho nút Lưu chung.

- X, Escape, backdrop và liên kết Hủy đóng trực tiếp. Frame được giữ trong trang cha nên dữ liệu nhập, dòng kế hoạch và tệp đã chọn không mất khi mở lại. Không tự ghi server, không lưu bản nhập xuống ổ đĩa; tải lại hoặc rời trang cha kết thúc bản nhập này.
- Lưu dùng submit HTML gốc; `app-ui.js` chống gửi lặp. Lỗi validate và conflict được hiển thị trong frame. Thành công có flash sẽ đóng frame đã lưu và làm mới nội dung chính, số thông báo, tên tài khoản/ngữ cảnh; giữ vị trí cuộn, bộ lọc, trang và lựa chọn. Nếu trang chính có bản nhập chưa lưu thì giữ nó và thông báo tải lại thủ công.
- Bulk chuyển chính các cặp task/revision đã chọn vào form. Backend tiếp tục kiểm quyền, revision và tính nguyên tử. Dùng `getAttribute('action')` vì trường `name="action"` che khuất thuộc tính `form.action` trong DOM.
- Dialog khóa cuộn nền, trả focus về nút mở khi đóng; frame xử lý Escape. Màn hình nhỏ dùng khoảng cách viền 6px và nút đóng/lưu tối thiểu 44px.
- Muốn thêm form inline: đặt `data-form` rõ nghĩa, thêm nhãn vào `inlineForms`, giữ URL action thật. Không thêm endpoint hoặc một controller/service chỉ để phục vụ dialog.
- Quy ước tên `data-form` mà `page-editor.js` dựa vào: form có tên chứa `delete`, `cancel`, `logout`, `status`, `comment`, `attachment` **không** nhận nút "Lưu thay đổi" chung của dialog (chúng giữ nút riêng trong form). Form còn lại chỉ nhận nút chung khi là form duy nhất có trường nhập trên trang, hoặc được mở trực tiếp từ launcher. Đặt tên form mới theo quy ước này thay vì sửa regex.
- Link "Hủy" trong form muốn đóng dialog thay vì chuyển trang thì thêm `data-editor-cancel`; editor không nhận diện theo chữ trên link.
- Dialog giữ tối đa 5 frame đã đóng; frame cũ nhất không có bản nhập dở sẽ bị bỏ trước. Rời trang cha khi còn frame có bản nhập dở sẽ được trình duyệt hỏi lại.
- Route riêng và liên kết “Mở trang riêng” là đường dự phòng. JavaScript không có hoặc dialog không được hỗ trợ thì link/form HTML vẫn hoạt động.

## Đánh giá bảo trì và phạm vi kiểm chứng

Đợt này giảm 18 class ròng so với trước refactor (17 lớp được hợp nhất, 1 provider không dùng được bỏ), không thêm service bọc. Các lớp lớn còn lại có phạm vi nghiệp vụ rõ; thuật toán cây, xếp lịch, preview, nguồn danh sách dùng chung và adapter bên ngoài vẫn tách vì có trách nhiệm riêng. Điểm đánh đổi: controller/service cùng module dài hơn, và mỗi bản nhập đang giữ mở chiếm một frame trong bộ nhớ. Không khẳng định đã loại hết mọi đoạn viết gọn hay đã kiểm mọi tổ hợp quyền/trình duyệt.

Bằng chứng backend: 250 yêu cầu HTTP và 141 kiểm tra dữ liệu; 166 hợp đồng route không đổi. Giao diện: kiểm tra Chrome trên desktop và viewport 390×844, cộng 8 lần đọc lại DB cho workspace/task/plan/calendar/comment/conflict và khóa ngoại. Bước xác nhận lưu bulk trong trình duyệt bị dừng bởi công cụ không xác định chắc URL trên Windows; không ghi nhận bước đó là đạt. Backend bulk/revision/scope đã được kiểm bằng HTTP thật trước đó. Không thử cạnh tranh trên DB production, email ngoài hệ thống hoặc nhà cung cấp AI thật; không deploy.

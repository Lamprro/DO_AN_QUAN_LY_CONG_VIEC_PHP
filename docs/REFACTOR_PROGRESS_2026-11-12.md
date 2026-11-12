# Tổ chức lại mã nguồn và luồng biểu mẫu

Đây là nhật ký của đợt 12/11/2026. Các báo cáo trước được giữ làm lịch sử.

## Nhóm 1 — tài khoản, workspace, project

- `RegistrationService.register` về `AccountService.register`: tạo tài khoản và workspace cá nhân là một giao dịch của nghiệp vụ tài khoản. Mail vẫn ở `AccountMailService` vì có lỗi/giao tiếp bên ngoài riêng.
- `WorkspaceQuery` về `WorkspaceService`: màn hình danh sách, tổng quan, thành viên và vai trò ở cùng nơi với hành động tương ứng. `WorkspaceMemberController` và `RoleController` về `WorkspaceController`, với các tên `members`, `updateMember`, `roles`, `createRole`, `storeRole`…
- `ProjectQuery` về `ProjectService`; `ProjectMemberController` về `ProjectController`. Đường dẫn, tên route, phương thức HTTP và middleware được giữ nguyên.
- `AccountService.resetPassword` dùng các khối xử lý tường minh, giữ thứ tự khóa → giao dịch → kiểm token → đổi mật khẩu và hủy phiên.
- Giữ `InvitationService` vì vòng đời token, gửi mail, thu hồi/chấp nhận khác với cập nhật membership. Giữ Policy/Access dùng chung; model không nhận nghiệp vụ phân quyền.

Kiểm chứng: review SQLite riêng, mail dùng bộ nhớ, AI tắt. Trước thay đổi: 39 yêu cầu HTTP/13 kiểm tra dữ liệu cho tài khoản–workspace–project; 48 HTTP/53 kiểm tra tổng hợp task cá nhân. Route baseline có 166 route. Kết quả sau mỗi nhóm được ghi bổ sung khi chạy xong, không dùng test giả hoặc dữ liệu sản xuất.

Các nhóm bên dưới đã được triển khai theo từng commit; giới hạn kiểm chứng được ghi rõ ở cuối báo cáo.

Nhóm 1 sau thay đổi: 39/39 HTTP, 13/13 kiểm tra dữ liệu; hồi quy cá nhân 48/48 HTTP, 53/53 kiểm tra. So sánh đủ 166 hợp đồng route: không đổi. Bằng chứng: docs/verification/readability-2026-11-12/identity-*.json.

## Nhóm 2 — công việc, mẫu công việc và thời gian

- Bỏ `TaskUtilityService`: bộ lọc và bulk về `TaskService`; watcher, dữ liệu cộng tác và tệp về `TaskWorkflowService`, dùng trực tiếp cơ chế `locked` của cùng lớp. Không thêm service mới và không tạo dependency vòng.
- Bỏ `TaskUtilityController`: bộ lọc có các tên rõ `storeFilter`, `openFilter`, `destroyFilter` trong `TaskController`. Bỏ `TaskCollaborationController`: watcher có `watch`/`unwatch` trong `TaskWorkflowController`.
- Bỏ `TaskTemplateQuery`: đọc mẫu và lịch sử chạy ở `TaskTemplateService`, cùng nơi tạo/cập nhật/khởi tạo kỳ mẫu.
- `TimeAdjustmentController` và `WorkHoursController` về `TimeTrackingController`: `editAdjustment`, `updateAdjustment`, `editWorkHours`, `updateWorkHours`. `TimeTrackingService` tiếp tục khóa dữ liệu, kiểm phiên bản và ghi audit; giờ làm nằm trong `users.settings`.
- Giữ `TaskPageData` vì có list/board/tree/form/panel; giữ `TaskListingQuery` vì phạm vi tổng hợp dùng cả trang task lẫn số đếm workspace. Giữ `TaskTreeService` cho thuật toán cây, `TaskParticipants` cho danh sách người hợp lệ dùng chung, `TaskHistory` cho audit + notification.

Kiểm chứng sau thay đổi: 36 HTTP/11 kiểm tra task–timer–template; 12 HTTP/5 kiểm tra tệp và conflict 409; 48 HTTP/53 kiểm tra tổng hợp cá nhân; 27 HTTP/31 kiểm tra thu hồi quyền, membership, đăng ký và bảo vệ workspace cá nhân. Đủ 166 hợp đồng route không đổi. Các JSON `tasks-*.json`, `files-conflict-results.json` nằm trong thư mục bằng chứng của đợt này. Không gọi AI/mail bên ngoài.

## Nhóm 3 — lịch, kế hoạch, báo cáo và AI

- `CalendarEventController`, `TimelineController`, `TeamCalendarController` thành `CalendarController`. `CalendarViewService` đổi tên đúng vai trò thành `CalendarPageData`; controller kiểm đầu vào HTTP, lớp này tính khoảng lịch và dữ liệu hiển thị từ các giá trị truyền vào.
- `PlanQuery` và `PlanEditorData` thành `PlanPageData`. Chuẩn bị bản nháp, ánh xạ task của phiên bản nguồn và phục hồi dữ liệu form chuyển khỏi `PlanController` vào `PlanPageData.form`. Controller giữ phản hồi HTTP và ánh xạ lỗi về trường form.
- `ReportQuery` về `ReportService`, gồm dashboard, lịch sử snapshot/AI, giới hạn phân trang và CSV. Các truy vấn vẫn kiểm lại quyền nguồn hiện tại.
- `PlanningAiController` về `AiRunController` qua `createPlanning`/`storePlanning`. `AiRunService` vẫn quản lý idempotency, hàng đợi, trạng thái và kết quả.
- Giữ `CalendarService` và `TeamCalendarService` vì lịch của chính mình khác với vòng đời đề xuất cần người nhận chấp thuận. Đề xuất là chính bản ghi `calendar_events`; chấp nhận đổi trạng thái, không nhân đôi. Giữ `PlanReviewService` cho diff/preview rollback, `PlanScheduleService` cho thuật toán xếp lịch, `PlanService` cho schema, phiên bản, duyệt và áp dụng.

Kiểm chứng: 56 HTTP/16 kiểm tra dữ liệu đạt; bao gồm tạo/sửa/xóa lịch, phiên bản cũ bị chặn, đề xuất/chấp nhận/hủy không tạo bản ghi trùng, draft không tạo task, preview rollback, duyệt/apply lặp không nhân đôi, snapshot/print/CSV, AI idempotency và worker thật với AI tắt. Worker không gọi nhà cung cấp và không tạo plan. Đủ 166 hợp đồng route không đổi. Bằng chứng: `planning-results.json`.

## Nhóm 4 — làm rõ nghiệp vụ trên toàn ứng dụng

- Tách các chuỗi truy vấn dài, mảng dữ liệu và các câu lệnh dính nhau trong planning, AI/job, calendar, report, reminder, invitation, integration, authorization, search, settings và task. So sánh cây cú pháp xác nhận các phần chỉ trình bày không đổi phép tính/điều kiện.
- Gộp hai đoạn kiểm tra replay AI vào `AiRunService.existingRequest`; vẫn kiểm quyền hiện tại trước khi trả yêu cầu cũ, so sánh digest và giữ thông báo lỗi của từng luồng.
- `GithubIntegrationController` lấy body/header HTTP rồi chuyển nguyên byte ký sang `GithubWebhookService`; service không còn nhận Request. Vẫn kiểm chữ ký và quyền lần nữa trong giao dịch, dedupe delivery, không chuyển trạng thái task từ commit.
- Dùng tên biến rõ trong diff kế hoạch, lịch đề xuất, giờ làm, báo cáo, queue và tìm kiếm; bỏ dependency được inject lặp trong cùng controller. Xóa `UnconfiguredAiProvider` không còn được đăng ký/tham chiếu; fallback thực tế vẫn là `HttpAiProvider` và cờ tắt nằm trong job.
- Không đổi model/observer, migration, rule nghiệp vụ, thứ tự khóa, revision, CSRF hay Policy/Access dùng chung.

Hồi quy tổng thể: **250 yêu cầu HTTP, 141/141 kiểm tra dữ liệu đạt**, cộng worker database thật cho AI tắt và nhắc việc in-app. Kiểm tra webhook dùng secret giả và payload ký thật trong môi trường review; không gọi GitHub bên ngoài. Có một lượt quét nhắc việc bị 429 do chạy lại quá gần; giữ nguyên throttle, chờ hết cửa sổ rồi chạy lại đạt. Các lỗi thiết lập kiểm chứng (CSRF sau login, scope proposal, throttle) được sửa ở kịch bản kiểm chứng, không sửa nghiệp vụ để ép đạt.

Giới hạn: SQLite riêng trên máy này, không chứng minh tranh chấp đồng thời ở database sản xuất. Không thử nhà cung cấp AI thật hoặc email ngoài hệ thống. Giữ nguyên key, database và cấu hình đang dùng. Khung form lớn và đồng bộ sheet được ghi ở các mục tiếp theo.

## Nhóm 5 — khung chỉnh sửa lớn

Commit `b39f0bc` — Add spacious editors across business forms. Thay task-panel riêng bằng editor dùng chung; trang nền còn hiển thị, X/Esc/backdrop đóng ngay và giữ bản nhập trong trang hiện tại. Tái dùng nguyên Blade để các form kế hoạch nhiều dòng, HTTP validation và đối chiếu 409 không mất hành vi. Bao phủ link tạo/sửa và các form inline có dữ liệu nhập; giữ các nút chuyển trạng thái riêng và thao tác tức thời.

Đã quan sát trên Chrome: tạo workspace và cập nhật danh sách nền; task lỗi mã trùng giữ dữ liệu rồi lưu lại; X/Esc/backdrop giữ bản nhập; focus trả về nút mở; form thành viên lưu; kế hoạch hai dòng giữ qua Esc rồi lưu nháp; conflict chọn mine lưu đúng; double-click lịch chỉ tạo một bản ghi; bình luận lưu; bulk mở đúng một dòng đã chọn. Viewport desktop: khung rộng 1488px, chừa viền 24px; viewport 390×844: khung 378×832, nội dung không tràn ngang.

8/8 kiểm tra đọc lại DB đạt. Blade toàn bộ biên dịch được; JavaScript kiểm cú pháp đạt; đủ 166 hợp đồng route giữ nguyên. Ảnh và JSON ở `docs/verification/readability-2026-11-12/editor-*`.

Giới hạn riêng: thao tác xác nhận lưu bulk chưa hoàn tất trên trình duyệt. Công cụ CUA đã dừng vì không xác định chắc URL đang mở trên Windows; không tiếp tục điều khiển hoặc coi hộp xác nhận đó là thành công. Kiểm chứng HTTP về bulk nguyên tử, quyền và revision vẫn đạt ở nhóm backend. Bản nhập chỉ tồn tại khi trang cha còn mở, không phải tính năng tự lưu dài hạn.

## Nhóm 6 — tài liệu và sheet hiện hành

Đã cập nhật trực tiếp sheet **ĐỒ ÁN PHP**, tab **P2 - Flow trong hệ thống** (gid 820682619), các vùng `A1:H48`, `A49:H213`, `A214:H445`, `A446:H589`, `A590:H800`: 1.338 ô trên 674 dòng. Giữ nguyên cấu trúc, sáu công thức điều hướng, validation và toàn bộ định dạng ô; không ghi sang tab khác. Đọc lại qua connector xác nhận mọi giá trị đúng bản dự kiến và ô ngoài danh sách sửa không đổi. Các phương thức Model mới liên quan được tham chiếu bổ sung cùng dòng model để không xê dịch nhóm.

Nội dung gồm map controller/service mới, signature và dòng code hiện tại; TaskListingQuery và phạm vi tổng hợp cá nhân; watcher về TaskWorkflowController/TaskWorkflowService; settings ở users.settings; proposal trong calendar_events; ProjectAccess.resolve là quyền dự án; blocker khác dependency; flow editor chung và giới hạn kiểm chứng. Các lời gọi trong bảng là phân tích tĩnh trực tiếp, không thay thế việc đọc callback/framework.

Không hoàn tất kiểm tra hiển thị trực tiếp trong Google Sheets vì CUA đã dừng. Đã kiểm tra lại định dạng bằng API, không tự đổi chiều rộng, hàng, wrap hay merge để che vấn đề. Bằng chứng: `sheet-verification.json`, `sheet-write-log.json`.

Liên kết: https://docs.google.com/spreadsheets/d/1--DCOQtPtV2o8MhNlMQtk4s3DJVcgHXdUhZrxMHEnDs/edit#gid=820682619

## Các commit theo nhóm

- `42dacbd` — Consolidate account and workspace flows.
- `c8ef354` — Unify task and time tracking flows.
- `8491f08` — Unify planning and calendar entry points.
- `2a52399` — Clarify domain workflows and onboarding.
- `b39f0bc` — Add spacious editors across business forms.
- Commit tài liệu cuối chứa hướng dẫn editor, báo cáo này và bằng chứng đối chiếu sheet.

Tất cả được đẩy trực tiếp lên main theo chỉ đạo, không force push và không deploy. Lịch sử trước đợt refactor, báo cáo của chat khác và nguồn đồng bộ của project được bảo toàn.

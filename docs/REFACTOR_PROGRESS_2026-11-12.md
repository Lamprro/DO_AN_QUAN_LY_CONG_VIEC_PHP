# Tổ chức lại mã nguồn và luồng biểu mẫu

Đây là nhật ký của đợt 12/11/2026. Các báo cáo trước được giữ làm lịch sử.

## Nhóm 1 — tài khoản, workspace, project

- `RegistrationService.register` về `AccountService.register`: tạo tài khoản và workspace cá nhân là một giao dịch của nghiệp vụ tài khoản. Mail vẫn ở `AccountMailService` vì có lỗi/giao tiếp bên ngoài riêng.
- `WorkspaceQuery` về `WorkspaceService`: màn hình danh sách, tổng quan, thành viên và vai trò ở cùng nơi với hành động tương ứng. `WorkspaceMemberController` và `RoleController` về `WorkspaceController`, với các tên `members`, `updateMember`, `roles`, `createRole`, `storeRole`…
- `ProjectQuery` về `ProjectService`; `ProjectMemberController` về `ProjectController`. Đường dẫn, tên route, phương thức HTTP và middleware được giữ nguyên.
- `AccountService.resetPassword` dùng các khối xử lý tường minh, giữ thứ tự khóa → giao dịch → kiểm token → đổi mật khẩu và hủy phiên.
- Giữ `InvitationService` vì vòng đời token, gửi mail, thu hồi/chấp nhận khác với cập nhật membership. Giữ Policy/Access dùng chung; model không nhận nghiệp vụ phân quyền.

Kiểm chứng: review SQLite riêng, mail dùng bộ nhớ, AI tắt. Trước thay đổi: 39 yêu cầu HTTP/13 kiểm tra dữ liệu cho tài khoản–workspace–project; 48 HTTP/53 kiểm tra tổng hợp task cá nhân. Route baseline có 166 route. Kết quả sau mỗi nhóm được ghi bổ sung khi chạy xong, không dùng test giả hoặc dữ liệu sản xuất.

Các nhóm task/time, lịch/kế hoạch/báo cáo/AI, việc làm rõ các nghiệp vụ còn lại và khung form lớn đang triển khai; chưa coi là hoàn tất.

Nhóm 1 sau thay đổi: 39/39 HTTP, 13/13 kiểm tra dữ liệu; hồi quy cá nhân 48/48 HTTP, 53/53 kiểm tra. So sánh đủ 166 hợp đồng route: không đổi. Bằng chứng: docs/verification/readability-2026-11-12/identity-*.json.

## Nhóm 2 — công việc, mẫu công việc và thời gian

- Bỏ `TaskUtilityService`: bộ lọc và bulk về `TaskService`; watcher, dữ liệu cộng tác và tệp về `TaskWorkflowService`, dùng trực tiếp cơ chế `locked` của cùng lớp. Không thêm service mới và không tạo dependency vòng.
- Bỏ `TaskUtilityController`: bộ lọc có các tên rõ `storeFilter`, `openFilter`, `destroyFilter` trong `TaskController`. Bỏ `TaskCollaborationController`: watcher có `watch`/`unwatch` trong `TaskWorkflowController`.
- Bỏ `TaskTemplateQuery`: đọc mẫu và lịch sử chạy ở `TaskTemplateService`, cùng nơi tạo/cập nhật/khởi tạo kỳ mẫu.
- `TimeAdjustmentController` và `WorkHoursController` về `TimeTrackingController`: `editAdjustment`, `updateAdjustment`, `editWorkHours`, `updateWorkHours`. `TimeTrackingService` tiếp tục khóa dữ liệu, kiểm phiên bản và ghi audit; giờ làm nằm trong `users.settings`.
- Giữ `TaskPageData` vì có list/board/tree/form/panel; giữ `TaskListingQuery` vì phạm vi tổng hợp dùng cả trang task lẫn số đếm workspace. Giữ `TaskTreeService` cho thuật toán cây, `TaskParticipants` cho danh sách người hợp lệ dùng chung, `TaskHistory` cho audit + notification.

Kiểm chứng sau thay đổi: 36 HTTP/11 kiểm tra task–timer–template; 12 HTTP/5 kiểm tra tệp và conflict 409; 48 HTTP/53 kiểm tra tổng hợp cá nhân; 27 HTTP/31 kiểm tra thu hồi quyền, membership, đăng ký và bảo vệ workspace cá nhân. Đủ 166 hợp đồng route không đổi. Các JSON `tasks-*.json`, `files-conflict-results.json` nằm trong thư mục bằng chứng của đợt này. Không gọi AI/mail bên ngoài.

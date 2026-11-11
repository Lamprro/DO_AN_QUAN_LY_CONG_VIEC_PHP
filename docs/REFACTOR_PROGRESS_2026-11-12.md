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

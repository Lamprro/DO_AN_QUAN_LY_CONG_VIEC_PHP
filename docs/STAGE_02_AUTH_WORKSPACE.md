# Phần 2 — Auth, Workspace, Role, Member

Ngày 17/10/2026. Phần 1 đã được chủ dự án duyệt. Phần 2 đã triển khai và xác minh local; dừng chờ duyệt trước phần 3. Không phải toàn bộ dự án đã hoàn thiện.

## Kiến trúc và đường đi chung

Trình duyệt gửi form Blade → `routes/web.php` → session/CSRF, `auth`, `active.user`, `workspace.member` khi tương ứng → Form Request kiểm tra đầu vào → Controller → Policy/Service → Eloquent + transaction → redirect → Controller GET → Blade.

Đây là MVC dùng session, không có REST API/SPA riêng. Client không được tự quyết định owner, loại workspace, quyền hệ thống hay trạng thái tài khoản. `scopeBindings()` giới hạn role/member theo workspace trên URL; tham chiếu từ workspace khác trả 404. Middleware kiểm tra membership hiện tại ở mỗi request, không tin `current_workspace_id` trong session để cấp quyền.

## 1. Đăng ký, đăng nhập, đăng xuất

- `RegisteredUserController::store(RegisterRequest, RegistrationService)` → `RegistrationService::register()` chuẩn hóa email và tạo User, UserSetting, workspace cá nhân trong transaction. `WorkspaceService::create(..., personal: true)` tạo 4 vai trò hệ thống và membership Owner. Sau commit đăng nhập và regenerate session, redirect về URL định đến hoặc danh sách workspace.
- `SessionController::store(LoginRequest)` → `LoginRequest::authenticate()` dùng `Auth::attempt()` với tài khoản active, giới hạn 5 lần sai/60 giây theo email + IP → regenerate session → redirect intended.
- `SessionController::destroy()` → logout, invalidate session, regenerate CSRF token → login.
- `EnsureUserIsActive::handle()` chặn tài khoản bị vô hiệu hóa, kết thúc phiên và trả 403. Không có chức năng quản trị trạng thái user trong phần 2.

## 2. Hồ sơ và mật khẩu

- `ProfileController::edit()` cung cấp user và danh sách timezone cho hai form riêng.
- `update(ProfileRequest)` chỉ nhận name/email/timezone; đổi email yêu cầu mật khẩu hiện tại. Khi email thay đổi, xóa mốc xác minh email, xoay remember token và thu hồi các DB session khác; regenerate phiên hiện tại.
- `password(PasswordRequest)` yêu cầu current_password đúng, mật khẩu mới xác nhận và đúng quy tắc; lưu qua hashed cast của User, xoay remember token, thu hồi các DB session khác và regenerate phiên hiện tại.
- Chưa có quên mật khẩu/email verification gửi qua nhà cung cấp thật. Không tạo link giả hoặc nút trỏ tới flow chưa có.

## 3. Workspace

- `WorkspaceController::index()` chỉ truy vấn membership active có quyền xem; tìm tên bằng q và phân trang 12 dòng, giữ query string.
- `store(WorkspaceRequest)` → `WorkspaceService::create()` tạo workspace team, role Owner/Manager/Member/Viewer theo config và membership Owner; owner lấy từ người đang đăng nhập. Ghi current_workspace_id để nhớ lựa chọn rồi mở show.
- `show()` kiểm tra policy, chuẩn bị owner, membership, số liệu và transferCandidates. `edit()/update()` → `WorkspaceService::update()` khóa dòng và kiểm tra quyền lại trước khi ghi name/timezone.
- `select()` kiểm tra quyền xem rồi chỉ đổi current_workspace_id trong session.
- `transfer(TransferOwnerRequest)` → `WorkspaceService::transfer()` kiểm tra Owner hiện tại, workspace team và người nhận active → transaction đổi owner_id, người nhận thành Owner, chủ cũ thành Manager. Quyền project riêng không bị ghi đè.
- `leave()` → `MemberService::leave()` đánh dấu left, giữ lịch sử. Owner phải chuyển chủ trước khi rời.
- `destroy()` → `WorkspaceService::delete()` chỉ Owner, chỉ workspace team trống, không có dữ liệu nghiệp vụ/lời mời chờ; xóa membership/role rồi workspace trong transaction. Không xóa dây chuyền task/project để vượt điều kiện.

## 4. Vai trò và quyền

- `WorkspaceAccess::membership()/permissions()/allows()` đọc membership active và permissions từ DB. Owner active có catalog quyền; role khác lấy giao giữa JSON permissions và catalog cấu hình.
- `WorkspacePolicy` kiểm tra view/update/manageRoles/manageMembers/delete/transfer; `RolePolicy` bảo vệ role hệ thống.
- `RoleController::store()/update()` → `RoleService::save()` khóa workspace, kiểm tra quyền quản lý, tên role không trùng và `WorkspaceAccess::assertGrantable()`; chỉ cấp được tập con quyền actor đang có.
- `RoleController::destroy()` → `RoleService::delete()` chặn role hệ thống, role vượt quyền và role đang được membership workspace/project tham chiếu.
- Không chỉnh/xóa Owner, Manager, Member, Viewer hệ thống qua form role. Role Owner không được gán bằng quản lý member/lời mời; chỉ flow transfer owner được phép.

## 5. Thành viên và lời mời

- `WorkspaceMemberController::index()` cung cấp members kèm user/role, assignableRoles, pendingInvitations và actorPermissions cho Blade.
- `update(MemberRequest)` → `MemberService::update()` khóa workspace, kiểm tra quyền và đối tượng, chỉ ghi role_id/status. Không sửa chính mình/Owner qua màn quản lý; không quản lý role vượt quyền mình; không kích hoạt user inactive.
- `destroy()` → `MemberService::remove()` đánh dấu left; không xóa membership cũ.
- `InvitationController::store(InvitationRequest)` → `InvitationService::issue()` dùng cache lock theo workspace và SQL transaction; kiểm tra team/quyền/role/email chưa là member active hoặc suspended → token ngẫu nhiên 64 ký tự, lưu SHA256 + payload có TTL → gửi qua Laravel Mail theo cấu hình. Link chỉ flash cho người mời sau khi tạo, không lưu token thô trong DB.
- `show(token)` → `find()` kiểm tra TTL/workspace và hiển thị trang chấp nhận. Guest được lưu intended URL để quay lại sau login/register.
- `accept(token)` → `InvitationService::accept()` khóa cache, đọc lại token và email đúng người nhận; khóa workspace, kiểm tra lại quyền hiện tại của người mời/role → tạo membership active hoặc kích hoạt lại bản ghi left → SQL commit → tiêu thụ token. Token dùng lại/hết hạn/thu hồi trả 410. Member suspended không thể tự dùng link để vượt đình chỉ.
- `destroy()` → `revoke()` thu hồi token theo ID lời mời và quyền quản lý. Mời lại cùng email thay link cũ.

## Cấu hình local và giới hạn đã xác minh

- DB thực tế: SQLite `database/work_management.sqlite`; session database. Không chạy migrate:fresh, không đổi APP_KEY cũ.
- Đã cài Predis bằng Composer và cấu hình `REDIS_CLIENT=predis`. Máy hiện chưa có Redis server. Local chọn tường minh `INVITATION_CACHE_STORE=file`: TTL, lock, chấp nhận/thu hồi thực sự hoạt động bằng Laravel cache. Không báo đã chạy Redis thật. Production bắt buộc redis trong InvitationService, không tự fallback.
- TTL mặc định 1440 phút, cấu hình 1..10080. Luồng hết hạn đã dùng TTL thật 60 giây, quan sát 200 trước hạn và 410 sau hạn.
- `MAIL_MAILER=log` local ghi email vào log, không gửi ra hộp thư. `mail_sent` trong service biểu thị transport đã nhận, không chứng minh email đã tới người nhận. Cần SMTP thật và kiểm tra phát thư khi có cấu hình.
- APP_URL phải khớp URL khi tạo link qua CLI/queue; HTTP tạo link theo host request. Không commit .env, APP_KEY, DB, cookie hoặc link mời còn hiệu lực.
- Chưa kiểm tra Redis/SMTP/MySQL thật, tải đồng thời nhiều tiến trình hay môi trường production. Phần project/task, lịch, AI, báo cáo và tích hợp chưa triển khai HTTP.

## Kết quả và điểm dừng

Minh chứng tại `docs/verification/stage02/README.md`. Có flow HTTP sử dụng cookie/CSRF thật và đọc SQL sau ghi; có thao tác trình duyệt tạo workspace. Không thêm lớp test hoặc mock. Frontend có form chạy được và CSS tối thiểu, xem `docs/FRONTEND_HANDOFF.md` để tiếp tục thiết kế. Dừng phần 2, chờ chủ dự án duyệt flow.

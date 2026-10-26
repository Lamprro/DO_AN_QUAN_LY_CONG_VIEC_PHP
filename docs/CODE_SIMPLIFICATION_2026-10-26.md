# Tinh gọn lớp nghiệp vụ — 26/10/2026

## Phạm vi và kết quả

Gộp 4 class vào các service nghiệp vụ hiện có, không thêm thư mục, class trung gian hoặc lớp kiểm thử. Giữ Laravel MVC/Blade, URL, method HTTP, tên route, dữ liệu form và schema database.

| Lớp cũ đã bỏ | Nơi xử lý hiện tại | Hàm |
|---|---|---|
| MemberService | WorkspaceService | updateMember, removeMember, leave |
| RoleService | WorkspaceService | saveRole, deleteRole |
| ProjectMemberService | ProjectService | saveMember, removeMember |
| TaskUtilityScope | TaskUtilityService | checkScope, indexUrl |

WorkspaceService gom thao tác workspace, thành viên và vai trò. ProjectService gom thao tác dự án và thành viên dự án. TaskUtilityService tự xử lý phạm vi và URL của tiện ích task. Controller gọi trực tiếp service tương ứng; không giữ class cũ làm wrapper.

TaskUtilityController dùng tên biến request, validated, workspace, project thay cho viết tắt; khối validation và vòng lặp được trình bày tách dòng. Các file PHP thay đổi được định dạng bằng Pint.

## Flow để bảo trì

1. Form Blade → web route → FormRequest kiểm tra đầu vào/quyền → Controller → service → transaction, khóa bản ghi và Gate → Eloquent → redirect/flash.
2. RoleController.store/update → WorkspaceService.saveRole: khóa workspace, kiểm tra quyền có thể cấp, tên trùng, quyền sửa role → lưu. destroy → deleteRole: chặn role còn được gán.
3. WorkspaceMemberController.update/destroy → updateMember/removeMember: bảo vệ Owner/chính mình, kiểm tra role trong workspace, tài khoản hoạt động → cập nhật membership. WorkspaceController.leave → leave: Owner phải chuyển chủ trước khi rời.
4. ProjectMemberController.store/update/destroy → ProjectService.saveMember/removeMember: khóa workspace/project/member, kiểm tra quyền cấp, membership workspace, không gán Owner → thêm/sửa hoặc đánh dấu left. Tham gia lại tái sử dụng bản ghi cũ.
5. TaskUtilityController → TaskUtilityService.checkScope → saveFilter/bulk/indexUrl. Bộ lọc cá nhân vẫn qua bound để đối chiếu user/workspace/project; không được xem bộ lọc người khác. Cập nhật hàng loạt vẫn giữ kiểm tra revision và transaction.

## Ranh giới được giữ lại

- Controller, FormRequest, Model, Policy là các vai trò rõ ràng của Laravel; không gom tất cả thành một class tổng.
- WorkspaceAccess/ProjectAccess dùng chung cho nhiều policy và service, cần giữ một nơi kiểm tra quyền nhất quán.
- TaskHistory ngắn nhưng là điểm ghi lịch sử và phát thông báo dùng chung nhiều nghiệp vụ; gộp vào TaskService sẽ làm các service khác phụ thuộc không cần thiết và có thể tạo vòng phụ thuộc với TaskTreeService.
- Lịch, kế hoạch, workflow task, queue và nhà cung cấp AI có xử lý độc lập; không gộp chỉ để giảm số file.
- RegistrationService quản lý giao dịch tạo tài khoản, setting và workspace cá nhân; không đưa nghiệp vụ tài khoản vào service workspace.

Quy tắc tiếp tục: ưu tiên thêm method có tên rõ nghĩa vào service đúng nghiệp vụ. Chỉ tách class khi có trách nhiệm độc lập, được dùng lại hoặc giúp cô lập tích hợp/phân quyền. Không tạo interface/DTO/wrapper chỉ để chuyển lời gọi. Không gộp theo số dòng đơn thuần.

## Kiểm chứng trực tiếp

- 126 yêu cầu HTTP được ghi nhận; 38 đối chiếu được xác nhận. Không phải phần trăm bao phủ toàn hệ thống.
- Tạo/sửa/xóa role; trùng tên bị chặn; role đang dùng không xóa được.
- Gán/gỡ/kích hoạt membership; người không có quyền bị chặn; bảo vệ Owner; rời workspace và tham gia lại.
- Tạm ngừng/gỡ/tham gia lại project, giữ id và joined_at, chặn membership trùng.
- Tạo/mở/xóa filter; loại query không cho phép; bộ lọc của người khác trả 404; bulk cập nhật database.
- Task panel: tạo/sửa, validation 422, xung đột 409 và đối chiếu từng trường, deadline UTC, blocker/status, giới hạn Viewer, cách ly project, escape nội dung bình luận.
- 31 trang MVC trả 200 trên bản review MariaDB.
- PHP lint, Pint, route cache, biên dịch Blade, git diff --check đều đạt.
- Dữ liệu nghiệp vụ database chính trước/sau giống nhau; kiểm tra có ghi dữ liệu chỉ trong database review riêng. Không migration, không đổi .env/key/schema.

Lần chạy đầu dừng do kịch bản kỳ vọng 403 cho bộ lọc của người khác trong khi ứng dụng trả đúng 404 để giấu tài nguyên. Đã sửa kỳ vọng và chạy lại toàn bộ luồng thành công; không đổi chính sách ứng dụng để chiều kịch bản.

Giới hạn: đợt này xác minh HTTP/Blade/database, không đánh giá lại giao diện bằng thao tác chuột và không gọi nhà cung cấp email/AI/webhook thật. Không tuyên bố toàn bộ hệ thống không còn lỗi.

Minh chứng: docs/verification/compact-2026-10-26/flow-panels.json, flow-membership-filters.json, validation.json.

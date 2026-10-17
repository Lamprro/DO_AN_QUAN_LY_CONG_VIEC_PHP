# Phần 3 — Project, Project Member, CRUD Task

17/10/2026. Chủ dự án đã duyệt phần 2. Phần 3 triển khai logic, tương tác DB và các trang Blade chạy thật; dừng chờ duyệt, chưa sang phần 4.

## Phạm vi

Project CRUD và cha/con; project membership riêng; Task WORK/TODO có CRUD metadata, phân công, mã tự động/thủ công, tìm/lọc/sắp xếp/phân trang; archive/restore; Task cá nhân không thuộc project trong workspace personal. Không thêm migration vì nền phần 1 đã đủ schema.

Chưa triển khai thao tác cây GROUP/MILESTONE, chuyển trạng thái Task, review, dependency/blocker, bình luận/file, tính tiến độ và ghi activity tự động: các luồng này thuộc phần 4. Không cho form CRUD lách cập nhật status/task_type/parent_task_id/started_at/completed_at/archived_at hay chuyển workspace/project. Task cũ từ seed vẫn đọc được và sửa metadata theo quyền; giữ nguyên loại, trạng thái và scope.

## 1. Boundary phân quyền

`auth` → `active.user` → `workspace.member` → scoped route binding → Request/Policy/Service. `ProjectPolicy` và `TaskPolicy` đăng ký trong AppServiceProvider.

- `ProjectAccess::permissions()` trước hết yêu cầu user và workspace membership active, có workspace.view. Owner workspace có catalog quyền quản trị. Người khác phải có project membership active, dùng role riêng tại project. Không cộng quyền role workspace vào role project, không thừa kế role project cha xuống con.
- `ProjectAccess::visible()` áp dụng cùng boundary ở SQL cho danh sách và lựa chọn cha. Biết UUID không vượt policy. `project.view` là tiền đề cho mọi quyền trong project; Task còn yêu cầu task.view trước thao tác task.
- `ProjectAccess::canCreate()` yêu cầu workspace.view + project.view + project.create ở workspace, để role sao chép cho creator có thể đọc project vừa tạo. Người tạo chỉ nhận chính role workspace hiện tại, không tự thành Manager/Owner hay nhận thêm quyền.
- `ProjectAccess::grantable()` chặn quản lý/cấp role vượt tập quyền actor ở project. Không gán system Owner qua quản lý project; không sửa bản thân hoặc Owner workspace qua màn này. Owner workspace vẫn quản trị được kể cả không có dòng project membership; chỉ membership riêng của project không tạo ra quyền Owner workspace.
- Role project tham chiếu role thuộc workspace, nhưng chỉ các permission tương ứng nghiệp vụ được policy sử dụng. Thay role project không sửa role workspace. Thay role dùng chung có thể tác động các membership đang dùng role đó theo cơ chế phần 2.
- `{project}` nằm trong workspace; `{task}` nằm trong project; `{projectMember}` qua relation `Project::projectMembers()` chỉ thuộc project trên URL. Sai scope trả 404. Với namespace task cá nhân, `TaskController::bound()` còn bắt project_id=null, không nhận task của project trên URL cá nhân.

## 2. Project qua từng hàm

`ProjectController::index()` → authorize workspace view → validate filter/sort whitelist → `ProjectAccess::visible()` → paginator 12 dòng → projects/index. Không lộ project chưa được thêm membership; Owner là ngoại lệ quản trị.

`create()/edit()` chuẩn bị parents trong phạm vi có quyền và chưa archived. `store()/update(ProjectRequest)` → `ProjectService::save()` → transaction khóa workspace, đọc lại project và kiểm tra quyền hiện tại → chọn whitelist name/description/status/start_date/deadline/parent_project_id → kiểm tra quyền xem cha và cha chưa archived → Eloquent. Observer nền chặn khác workspace, chu trình, deadline trước start_date. Create thêm project membership của creator trong cùng transaction. Redirect show với flash.

Project status nhận draft/active/on_hold/completed/cancelled. Khi đổi completed, service từ chối nếu còn task khác DONE/CANCELLED hoặc project con khác completed/cancelled, kể cả bản archived. Không tự hoàn thành task hoặc project con. Đây chưa phải state machine Task.

`show()` chỉ hiển thị parent và children actor được xem; có creator/counts. `archive()/restore()` → `ProjectService::archive()` kiểm tra project.archive và ghi archived_at; project archived còn đọc được, chặn sửa, quản lý member và ghi task bên trong. Không archive dây chuyền project con vì quyền/scope con độc lập. Task giữ archived_at riêng.

`destroy()` → `ProjectService::delete()` chỉ người có project.archive; project phải draft, chưa archived và không có project con/task/plan/AI/calendar/GitHub/report/notification. Xóa membership rồi project trong transaction. Không xóa dữ liệu nghiệp vụ để ép project thành trống.

## 3. Project Member qua từng hàm

`ProjectMemberController::index()` → policy manageMembers → chuẩn bị members (workspaceMember.user, role), candidates (workspace member active, user active, chưa là member hoặc đã left), roles trong tập quyền được cấp, actorPermissions.

`store()/update(ProjectMemberRequest)` → `ProjectMemberService::save()` khóa workspace và project, kiểm tra manageMembers → tìm workspace member và role cùng workspace → chống tự sửa/Owner/vượt quyền → active chỉ khi user và workspace member active → tạo hoặc cập nhật role_id/status. Thêm lại người left tái sử dụng membership UUID, không tạo trùng.

`destroy()` → `remove()` → cùng save với status=left. Lịch sử tham chiếu còn giữ. Suspended/left ở workspace hoặc project chặn request tiếp theo ngay cả khi cookie session vẫn còn; project membership active không vượt suspension ở workspace.

## 4. Task qua từng hàm

Một TaskController phục vụ hai namespace: workspaces.projects.tasks.* trong project và workspaces.tasks.* cho personal. `context()` chuẩn bị routePrefix/routeParams, canCreate/canAssign và reviewers. `bound()` kiểm tra scope khi đọc/ghi.

`index()` → scope task.view → validate q/status/priority/assignee_id/archived/sort/direction/per_page → query theo đúng workspace + project_id (hoặc null) → eager load assignee/reviewer → sort whitelist + id ổn định → paginate 10/25/50, giữ query. Ưu tiên sort theo low < medium < high < urgent; q tìm title hoặc code, gồm cả q=0.

`create()/edit()` cung cấp people và reviewers, không query DB trong Blade. `store()/update(TaskRequest)` → `TaskService::save()` → khóa workspace/project/task trong transaction, đọc lại và authorize → whitelist metadata:

- code: chuẩn hóa uppercase, unique toàn workspace kể cả project khác/archived. Để trống khi create sinh T- + ULID, khi update giữ mã hiện tại; DB unique là bảo vệ cuối, lỗi collision trả validation.
- title/description/priority/estimate_minutes/deadline_at: validation độ dài, enum, số nguyên không âm, định dạng ngày. Non-WORK vẫn chịu invariant không estimate của phần 1.
- deadline_at nhập datetime-local theo timezone workspace, đổi UTC trước khi lưu. Blade chuyển về timezone workspace; không lấy timezone trình duyệt để suy đoán.
- assignee_id/reviewer_id: khi thực sự đổi cần task.assign; người được chọn phải active và có quyền xem Task trong project, reviewer còn cần task.review. `ProjectAccess::eligibleMembers()` cung cấp đúng các danh sách này; Owner active hợp lệ. Người sửa metadata không có task.assign không thấy các select và không làm mất phân công cũ.
- Người từng được phân công nhưng sau đó mất quyền vẫn có thể được giữ trong hồ sơ task để không tự sửa lịch sử; không được gán mới khi không đủ điều kiện. Chọn xóa phân công vẫn cần task.assign.
- Create ghi server-owned workspace_id/project_id/created_by, task_type WORK, status TODO. Field workflow/scope không nhận từ form. Không ghi activity tự động ở phần 3; phần 4 sẽ bổ sung history đúng sự kiện.

`TaskPolicy::projectWritable()` chặn ghi Task nếu project archived/completed/cancelled; project đóng phải mở lại trước.

`archive()/restore()` → `TaskService::archive()` yêu cầu task.archive, project chưa archived và chưa đóng → chỉ ghi archived_at. Task archived đọc được, không sửa, mặc định ẩn trong list; khôi phục trước khi sửa.

`destroy()` → `TaskService::delete()` yêu cầu task.archive, TODO, chưa started/completed, chưa archived; không children/dependency/blocker/calendar/time entry/activity (gồm soft-deleted)/GitHub/notification. Có liên quan thì giữ dữ liệu và yêu cầu archive. Không xóa dây chuyền HISTORY.

## 5. Blade và xác minh

Đã có projects/index/create/edit/show/_form, project-members/index, tasks/index/create/edit/show/_form, components/textarea, shared/list-controls và JS form-confirm. Điều hướng từ workspace tới Projects hoặc công việc cá nhân. HTML form, CSRF, method spoof, error summary, old input và policy có thật; FE tiếp tục giao diện theo FRONTEND_HANDOFF.md.

HTTP walkthrough dùng 3 tài khoản, cookie/CSRF và SQL read-back; có archive/restore/delete, chống vòng cha/con, scope chéo, không kế thừa quyền, đình chỉ membership, mã trùng, workflow field injection, phân công trái quyền, filter/sort/pagination, Task personal. Browser tạo Project rồi tạo/sửa Task BROWSER-P3-001; SQL đọc lại title đã sửa và estimate=75. Không thêm test class/mocks, không reset database.

Minh chứng: docs/verification/stage03/README.md. Môi trường xác minh là SQLite local, chưa chứng minh MySQL/Redis/SMTP, tải đồng thời hoặc các flow phần 4. Dừng chờ chủ dự án duyệt phần 3.

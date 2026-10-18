# Bàn giao FE — Laravel MVC / Blade

## Cập nhật mới nhất — Đợt D/P1 quan sát, 18/10/2026

Đã hoàn thiện Calendar/Timeline, dashboard/biểu đồ có bảng tương đương, snapshot/CSV, inbox lọc server, AI config/attempts và GitHub latest delivery. Đọc [P1_OBSERVATION.md](P1_OBSERVATION.md) trước các ghi chú P0/phần2–6, và [minh chứng P1](verification/p1-observation/README.md). Đợt E/F04–F10 chưa triển khai.

Calendar dùng `view=day|week|month|list`, `date`, `from/to` (exclusive end,≤93ngày), `include_cancelled`, `project_id` nullable. Controller nay thực sự lọc project_id; không bỏ tham số khi đổi chế độ. `navigation` cấp URL trước/sau/hôm nay và trục timeline; `range.timezone` là timezone người dùng. `calendar/create?task_id=...&project_id=...` chỉ chuẩn bị form từ eligible WORK của actor, chưa ghi lịch. Private busy không có title/owner/ID/link/task trong HTML. Timeline giữ offset/width server theo timestamp và danh sách tương đương.

`reports/_metrics` dùng metrics schema1 hiện có; bar/meter không làm phép tính nghiệp vụ tại client. `MetricDisplay::minutes` đổi cách đọc sang giờ/phút/giây; null khác0. CSV/snapshot giữ số phút gốc. `reports/_snapshot` cấp nhãn số liệu đã lưu/người tạo/kỳ/timezone/bộ lọc; không thay bằng metrics live nếu snapshot bị chặn quyền. Styles mới `public/css/p1-observation.css` được nạp sau p0-ui.css.

Inbox nhận `filter=all|unread|read`, `type=task.created|task.updated|task.status_changed|task.comment_added` optional, query/paginate server trước render. Giữ native forms read/unread/read-all, không đánh dấu đã đọc trước POST/PATCH thành công. AI partial `_configuration` chỉ mô tả readiness, không xuất key; retry giữ context cũ, có attempts còn lại, không giả kết quả/progress. GitHub `$latestDelivery` là log đã lưu theo project hiện tại, không chứng minh đã kiểm mạng ở lần mở trang.


## Bàn giao P0 hiện tại — đọc trước các ghi chú lịch sử

P0 đã triển khai. Đọc [P0_IMPLEMENTATION.md](P0_IMPLEMENTATION.md) để xem phạm vi, flow hàm và giới hạn; [verification/p0/README.md](verification/p0/README.md) để xem bằng chứng. Backlog UI_UX_FUNCTIONAL_IMPROVEMENTS.md vẫn chứa mô tả **trước nâng cấp**; P1/P2 chưa tự động thành chức năng đã làm.

### File FE chính và trách nhiệm

| File | Hợp đồng cần giữ |
|---|---|
| components/layout.blade.php + AppShell::build | shell.workspaces/workspace/project/menu/breadcrumbs/timer/timerTaskVisible; menu đã lọc quyền; không dựng link task khi timerTaskVisible=false |
| public/css/p0-ui.css | Token :root/theme và các lớp card/actions/status-pill/form-grid/mobile-data-table/editor/diff; loaded sau shell.css |
| public/js/app-ui.js | Native submit, CSRF, named submitter, error link, menu, dirty-form, mật khẩu/timezone/file và elapsed clock; đừng nạp lại form-confirm.js để tránh hỏi hai lần |
| tasks/_form, projects/_form | Hidden expected_revision bắt buộc khi sửa; không gửi revision để tự tăng. Giữ old input khi stale. ?fresh=1 là thao tác bỏ bản nhập để xem DB mới |
| tasks/workflow + TaskWorkflowService::availability | workflowUi.actions/notices; button status raw enum, expected_status, reason; enabled/reason là trình bày, service vẫn kiểm lại |
| plans/create, editor-task + plan-editor.js | Thao tác cây/FS/slot/ref; không đổi DOM rồi báo đã lưu khi chưa POST; template cũng phải dùng source results mới |
| plans/show/preview + PlanReviewService | Diff so parent và preview so DB là hai loại; preview không lưu nghiệp vụ; apply phải POST lại expected_status=approved |

### Form contract mới

- Task/Project edit: `_token`, `_method` theo route hiện có, `expected_revision` nguyên dương từ bản form đã mở. Không tự retry bằng revision mới nếu server báo stale; giữ nội dung cho người dùng đối chiếu.
- Project list: `view=cards|table`, q/status/archived/sort/direction và page giữ trên query string. View chỉ đổi cách trình bày dữ liệu phân trang từ server.
- Plan POST: `_token`, `name`, `project_id` nullable cho personal, `base_id` cho revision. `input_mode=editor`, `editor_enabled=1`, `payload` là JSON schema1 do JS đóng gói; editor fields không gửi hàng nghìn input name để tránh max_input_vars. Legacy chỉ payload vẫn tương thích. JSON nâng cao dùng `input_mode=json`, `json_payload` hoặc payload. Chế độ editor không JS bị báo rõ; có thể chọn JSON nâng cao để gửi native.
- Plan source GET: cùng route workspaces.plans.create, `source_q` ≤200 ký tự, `source_selected[]` ≤100 UUID. Không có endpoint API mới. Kết quả HTML chứa #plan-editor-data. Giữ draft hiện tại khi thay choices; không dùng fetch này để lưu.
- Giới hạn editor:100 task,300 FS,300 slot, cây3 tầng, text JSON≤120000 ký tự; server là nguồn kiểm cuối. Ref không đổi khi rename/reorder; array position được apply thành sort_order. Không ghép bằng title.
- Schema/field errors: `editor.tasks.N.field`, `editor.dependencies.N.field`, `editor.slots.N.field` theo payload nhận lại. Lỗi ref được hiển thị trong summary/row; không gắn index DOM cũ sau sắp xếp. Nguồn/assignee/reviewer ngoài quyền hoặc source không còn TODO sẽ bị server từ chối.
- GET `workspaces.plans.preview` `/workspaces/{workspace}/plans/{plan}/preview`: cần plan.apply+plan.approve và đúng scope. Preview hợp lệ không bảo đảm lần POST sau vẫn hợp lệ; không lưu valid flag ở client làm điều kiện bỏ recheck.
- DTO preview: valid, errors, tasks[{ref,title,action,changes}], events[{title,starts_at,ends_at,timezone}], replaced[{title,starts_at}], base_active_label. Không dùng UUID mô phỏng làm đường dẫn thật; UUID task tạo trong preview đã rollback.
- Phân biệt plan.status raw enum, applied_at, is_active. Hiển thị tiếng Việt chỉ trên label, không dịch `value`, `data-ai-status`, expected_status hoặc field enum khi gửi form.
- Native workflow button `name=status value=IN_PROGRESS|REVIEW|...`; khi disable submit button phải giữ named submitter trong POST. Không tự bật nút mà guard server đã disabled khi Back/Forward.
- Timer stop luôn route authorized, chỉ entry của chính actor; thanh toàn cục không được lộ task.title/code/scope/link nếu mất quyền. Tính giờ hiển thị không tự ghi TimeEntry.

### Luồng FE cần làm đẹp tiếp

Login → workspace picker → project → task list/tree → task detail → workflow/timer/blocker → plan editor → lưu draft → diff → propose → approve → preview → apply. Các link/form/Blade đã có. FE có thể đổi typography, spacing, bố cục/component, mobile và empty/loading/error presentation; giữ permission predicates, field names, old input, CSRF, private download và validation.

Không gọi service/provider/SQL trong Blade mới, không chuyển quyền thành CSS ẩn, không dựng báo cáo/actual giả từ estimate. Hạng mục Calendar drag, Kanban, autosave, email/AI/network vẫn theo P1/P2/KEY của backlog. Rà soát afterCommit/side effect nếu thay đường apply vì preview đang mô phỏng chính service trong transaction rollback.


Cập nhật 17/10/2026: các module backend/Blade trong phạm vi bàn giao đã được triển khai, gồm Plan/version/duyệt–apply, replanning/assistance qua queue, local scheduling, timer và GitHub logs. AI/provider và dịch vụ ngoài chờ key/cấu hình thật. Đọc [DELIVERY_STATUS.md](DELIVERY_STATUS.md) và mục **Bàn giao cuối** phía dưới để biết hợp đồng hiện tại. Những mục ghi theo phần2–6 là lịch sử tại thời điểm triển khai, không phải trạng thái thiếu chức năng hiện nay.

Kế hoạch nâng cấp tiếp theo: [UI/UX và chức năng chi tiết](UI_UX_FUNCTIONAL_IMPROVEMENTS.md), có ưu tiên P0/P1/P2, yêu cầu từng màn hình, phần cần bổ sung backend/database và flow nghiệm thu thật. P0 đã triển khai trong vòng hiện tại; P1/P2 vẫn là backlog, chưa phải chức năng mới đã làm. FE tiếp tục Laravel MVC/Blade trên form hiện có; backend chịu trách nhiệm logic/database/phân quyền.

## Bắt đầu

Đọc `IMPLEMENTATION_PROGRESS.md`, `STAGE_02_AUTH_WORKSPACE.md`, `verification/stage02/README.md`. Mở `/login`, dùng tài khoản local demo1@example.test / Demo@123456. Chạy `php artisan serve --host=127.0.0.1 --port=8123`; preview hiện tại http://127.0.0.1:8123. Không đổi APP_KEY hoặc reset DB.

Layout là `resources/views/components/layout.blade.php`, dùng `<x-layout title="...">`, CSS tại `public/css/app-shell.css`; không cần Node build để chạy các trang phần 2. Components có `input`, `timezone-select`. Có flash success/warning, lỗi validation và form CSRF chạy thật. Không dựng SPA/API mới, không gọi DB hoặc service từ Blade/JS.

## Trang có thật và dữ liệu được controller cung cấp

| Blade dưới resources/views | Dữ liệu có thật | Việc FE làm |
|---|---|---|
| auth/login, auth/register | old input, errors, session | Thiết kế auth, hiển thị lỗi, trạng thái gửi |
| profile/edit | user, timezones | Bố cục hai form hồ sơ/mật khẩu; giữ hai ID current_password khác nhau |
| workspaces/index | workspaces (paginator 12 dòng), query q | Card/list, tìm kiếm, phân trang, rỗng |
| workspaces/create, edit, _form | timezones, workspace ở edit | Form name/timezone, lỗi đúng trường |
| workspaces/show | workspace (owner + counts), membership (role), transferCandidates | Tổng quan, nút theo quyền, modal xác nhận chuyển chủ/rời/xóa |
| roles/index | workspace, roles (counts workspaceMembers/projectMembers) | Bảng role, trạng thái hệ thống, nút theo policy |
| roles/create, edit, _form | workspace, permissionCatalog; role ở edit | Checkbox permissions[] từ catalog server, mô tả quyền |
| members/index | workspace, members (user/role), assignableRoles, pendingInvitations, actorPermissions | Danh sách, đổi role/status, mời/thu hồi, sao chép link vừa tạo |
| invitations/show | invitation (array metadata), token | Thông tin người mời/email/role/hạn; login/register hoặc accept theo trạng thái |
| mail/workspace-invitation | invitation, url | Chỉnh bố cục email, giữ link backend |
| errors/403,404,410,419,429 | dữ liệu lỗi Laravel | Thiết kế lỗi quyền, hết hạn, session và giới hạn request |

Các tên biến trên đã implement. Không query trong Blade để bổ sung dữ liệu thiếu; yêu cầu backend thêm vào controller. Role.permissions là array, kind/status là enum (`->value`); timezone/dates xử lý theo dữ liệu server.

## Form gọi nhau như thế nào

GET mở trang → form action dùng `route()` bên dưới → POST kèm @csrf (PATCH/PUT/DELETE dùng @method) → Controller/Request/Service xử lý → redirect → GET render lại. Không cần gọi fetch để chạy phần 2. Tên trường và method là hợp đồng cần giữ khi thay HTML/CSS.

| Trang/form marker data-form | Route name / method | Fields người dùng gửi | Đích sau thành công |
|---|---|---|---|
| login | login.store POST | email, password, remember tùy chọn | intended hoặc workspaces.index |
| register | register.store POST | name, email, password, password_confirmation, timezone | intended hoặc workspaces.index |
| logout | logout POST | không có | login |
| profile | profile.update PATCH | name, email, timezone, current_password khi đổi email | quay lại profile |
| password | password.update PUT | current_password, password, password_confirmation | quay lại profile |
| workspace-search | workspaces.index GET | q | danh sách, giữ q khi phân trang |
| workspace-create | workspaces.store POST | name, timezone | workspaces.show |
| workspace-edit | workspaces.update PATCH | name, timezone | workspaces.show |
| chọn workspace | workspaces.select POST | không có, workspace trên URL | workspaces.show |
| owner-transfer | workspaces.transfer POST | member_id từ transferCandidates | workspaces.show, quyền được cập nhật |
| workspace-leave | workspaces.leave POST | không có | workspaces.index |
| workspace-delete | workspaces.destroy DELETE | không có | workspaces.index |
| role-create / role-edit | workspaces.roles.store POST / .update PATCH | name, permissions[] | workspaces.roles.index |
| xóa role | workspaces.roles.destroy DELETE | không có | workspaces.roles.index |
| member-update | workspaces.members.update PATCH | role_id, status (active/suspended/left) | workspaces.members.index |
| gỡ member | workspaces.members.destroy DELETE | không có | workspaces.members.index |
| invitation-create | workspaces.invitations.store POST | email, role_id | members.index + flash invitation_url |
| thu hồi lời mời | workspaces.invitations.destroy DELETE | invitation UUID trên URL | members.index |
| invitation-accept | invitations.accept POST | token trên URL | workspaces.show |

GET route: login, register, profile.edit, workspaces.index/create/show/edit, workspaces.roles.index/create/edit, workspaces.members.index, invitations.show. Tên/URI đầy đủ được xuất từ Laravel tại `verification/stage02/routes.json`; không thêm link tới endpoint chưa có.

Nested routes truyền `[$workspace,$role]`, `[$workspace,$member]` hoặc `[$workspace,$invitation['id']]`; invitations.show/accept truyền token. UUID không thay bằng ID số. Member_id khi transfer là ID membership, không phải user_id.

## Quyền và an toàn phải giữ khi sửa FE

- Giữ `@can('update', $workspace)`, `manageRoles`, `manageMembers` và policy update/delete role. Owner active có mọi quyền workspace; role khác đọc catalog server. Không hardcode Manager luôn có mọi quyền.
- Form role chỉ hiển thị permissionCatalog, form member chỉ dùng assignableRoles. Không đưa Owner vào select mời/gán role. Owner chỉ đổi qua transfer.
- Không quản lý chính mình hoặc Owner qua form member; không cho đối tượng có quyền cao hơn actor xuất hiện nút sửa/gỡ. Backend vẫn kiểm tra lại dù nút bị giả mạo.
- Workspace personal không mời người khác, chuyển chủ hoặc xóa. Owner muốn rời team phải chuyển chủ trước. Xóa chỉ workspace team trống. UI nên có modal xác nhận rõ tên đối tượng.
- Giữ @csrf/@method, name/action, old()/@error và error summary; giữ data-form/data-page markers để truy vết flow. Khi đổi ID input phải giữ label for tương ứng.
- Hiện lỗi 403/404/410/419/429 đúng tình huống; validation redirect về form giữ old input. Không biến lỗi thành toast thành công.
- Escape bằng {{ }}; không render HTML chưa kiểm soát. Không đưa credential/cookie/session/private token vào JS/log công khai. Link mời chỉ hiển thị qua session('invitation_url') ngay sau tạo cho người có quyền; các lời mời chờ không có link token thô để lấy lại.
- Members hiện là collection, chưa phân trang; đừng gọi links() lên collection. Workspace index là paginator có links(). Counts trên workspace show gồm mọi trạng thái membership.
- Đổi mật khẩu và đổi email thu hồi DB session khác. Đừng gộp hai form profile thành một request hoặc bỏ current_password.
- Email local dùng log, lời mời dùng file TTL mode tường minh; không hiển thị đã gửi email thật/Redis thật. Không đọc storage/logs từ FE.

## Việc AI giao diện cần hoàn thiện

Thiết kế responsive layout/sidebar/breadcrumb, màu/font/khoảng cách, bảng/card, thông báo, focus/accessibility, trạng thái rỗng/lỗi, nút copy invitation_url, xác nhận thao tác quan trọng, nhãn quyền dễ hiểu. Giữ submit HTML gốc hoạt động kể cả không có JS. Không thay đổi nghiệp vụ/backend nếu chưa trao đổi; nếu cần dữ liệu mới, ghi yêu cầu rõ controller/biến cần bổ sung.

Sau khi sửa, thao tác lại bằng browser thật: đăng nhập → tạo workspace → role → mời/nhận → sửa member → profile → logout. Kiểm tra lỗi quyền bằng hai tài khoản khác quyền. Không thêm test class theo chỉ đạo chủ dự án, không dùng mock để báo flow hoàn tất.

## Module tương lai — chưa có route/view xử lý

Project/project member/CRUD task đã có ở phần 3 (xem bổ sung cuối tài liệu); cây task, review, blocker, dependency, activity/file ở phần 4; lịch/settings/thông báo, báo cáo, AI và integrations ở các phần sau. Không thêm nút hoạt động giả hoặc route() tới tên chưa tồn tại.

Nền dữ liệu V3: 20 model/61 FK; task loại GROUP/WORK/MILESTONE, không có bảng goals riêng. Lịch TASK_BLOCK là lịch kế hoạch, không thêm planned_start_at vào tasks. HISTORY bất biến, COMMENT soft delete. AI fixture phải gắn nhãn mô phỏng; plan proposed chưa thay đổi task thật. Xem schema/planning và STAGE_01_DATABASE.md cho invariant nền. Không tự triển khai module tương lai trước điểm duyệt.


## Bổ sung phần 3 — hợp đồng đã hoạt động

Phần 2 đã được chủ dự án duyệt. Phần 3 có backend và Blade chạy thật, chờ duyệt; đọc `STAGE_03_PROJECT_TASK.md`. Phần dưới này là contract thực tế, không phải route đề xuất. Giữ toàn bộ hợp đồng phần 2 phía trên.

### Trang và biến

| View dưới resources/views | Biến backend cung cấp |
|---|---|
| projects/index | workspace, projects (paginator 12), filters, canCreate |
| projects/create, edit, _form | workspace, project (null khi tạo), parents (collection project được xem, chưa archived) |
| projects/show | workspace, project (creator + members_count/tasks_count), parent (chỉ khi có quyền), children (chỉ project được xem), canViewTasks |
| project-members/index | workspace, project, members (workspaceMember.user + role), candidates (workspace members), roles (cấp được), actorPermissions |
| tasks/index | workspace, project hoặc null, routePrefix, routeParams, canCreate, canAssign, reviewers, tasks (paginator), filters, people |
| tasks/create, edit, _form | các biến context như trên, task (null khi tạo), people, reviewers |
| tasks/show | các biến context như trên, task (assignee/reviewer/creator) |

`people`/`reviewers` là WorkspaceMember collection kèm user, option value là user_id. `candidates` dùng membership ID, không user_id. Project members dùng `workspaceMember` camelCase và role. Project/member/task status, task_type, priority là enum; hiển thị `->value`, không giả định chuỗi thuần.

### Route, fields và đích

| Form marker | Route / method | Fields | Redirect thành công |
|---|---|---|---|
| project-create | workspaces.projects.store POST | name, description, status, start_date (Y-m-d), deadline (Y-m-d), parent_project_id nullable | projects.show |
| project-edit | workspaces.projects.update PATCH | như trên | projects.show |
| project-search | workspaces.projects.index GET | q,status,archived (active/archived/all),sort (name/created_at/deadline),direction (asc/desc) | render list + giữ query |
| project-archive | workspaces.projects.archive hoặc .restore POST | không có | back |
| project-delete | workspaces.projects.destroy DELETE | không có | projects.index |
| project-member-create | workspaces.projects.members.store POST | workspace_member_id,role_id,status=active | back |
| project-member-update | workspaces.projects.members.update PATCH | role_id,status (active/suspended/left), không gửi workspace_member_id | back |
| project-member-delete | workspaces.projects.members.destroy DELETE | không có | back, đánh dấu left |
| task-create / task-edit | routePrefix.store POST / .update PATCH | code nullable,title,description,priority,estimate_minutes nullable,deadline_at nullable,assignee_id/reviewer_id chỉ nếu canAssign | routePrefix.show |
| task-search | routePrefix.index GET | q,status,priority,assignee_id,archived,sort (code/title/priority/deadline_at/created_at),direction,per_page=10/25/50 | render list + giữ query |
| task-archive | routePrefix.archive hoặc .restore POST | không có | back |
| task-delete | routePrefix.destroy DELETE | không có | routePrefix.index |

GET resource routes index/create/show/edit có thật. `routePrefix` là `workspaces.projects.tasks` khi có project, `workspaces.tasks` khi personal. `routeParams` là [$workspace,$project] hoặc [$workspace]. Detail/action dùng `route($routePrefix.'.show', [...$routeParams,$task])`; không tự ghép đường dẫn. Project members action truyền [$workspace,$project,$member] và route placeholder là projectMember. Route inventory phần 3 tại verification/stage03/routes.json.

deadline_at gửi `YYYY-MM-DDTHH:mm` theo workspace.timezone, backend đổi UTC. Render lại cũng theo workspace.timezone; không đổi sang giờ trình duyệt rồi gửi cùng giá trị. Code để trống khi update giữ mã cũ, khi tạo sinh mã mới. Không tự tính next code bằng số dòng hoặc gọi endpoint giả để lấy mã.

### Giữ nguyên logic khi thiết kế giao diện

- Đi từ workspace → Projects → project show → members hoặc tasks. Với personal có link Công việc cá nhân. Không đưa task của team vào namespace không project; không tự suy ra quyền từ project cha.
- Giữ canCreate/canAssign/canViewTasks và @can update/manageMembers/archive/delete. Server vẫn kiểm tra request. Chỉ có workspace.view chưa đủ xem project; thêm project membership riêng. Role project độc lập, Owner workspace có quyền quản trị.
- Người có task.update nhưng không task.assign chỉ sửa metadata; đừng thêm hidden assignee_id/reviewer_id rỗng vì sẽ biến lưu nội dung thành xóa phân công. Select reviewer lấy reviewers, không lấy people; reviewer mới cần task.review.
- Giữ lựa chọn phân công cũ hiện không đủ điều kiện để xem lịch sử; backend chỉ cho giữ nguyên hoặc đổi sang người đủ điều kiện, không cấp mới cho người inactive.
- Project completed/cancelled chặn mọi ghi Task; mở lại project trước. Archived project còn xem được, chặn ghi task/member; khôi phục project trước. Archive project không cascade project con. Task archived có trạng thái riêng.
- Xóa là xóa vĩnh viễn bản nháp đủ điều kiện, không phải soft-delete. Khi có dữ liệu/lịch sử, backend trả lỗi và giữ nguyên; dùng archive. Giữ data-confirm và public/js/form-confirm.js hoặc thay bằng modal xác nhận tương đương. Validation server vẫn chạy kể cả JS bị tắt.
- Layout x-layout đã nạp JS xác nhận bằng defer; textarea component escape text, shared/list-controls dùng filters và sortOptions. Giữ labels/CSRF/name/action/@method/error summary/data-form/data-page/data-task-id/data-project-id khi đổi markup.
- List dùng paginator links() và giữ query. Members/candidates/parents là collection. Ưu tiên sort có thứ tự nghiệp vụ, không sort nhãn alphabet. Không gửi tên cột SQL tự do.
- Chưa có endpoint chuyển trạng thái/tree/comment/upload/review; đừng thêm form status/task_type/parent_task_id vào CRUD. Những field này bị prohibited. Không có activity feed tự động/tiến độ ở phần 3 để FE tự suy diễn.

FE cần hoàn thiện bố cục responsive, nhãn trạng thái/permission dễ hiểu, bảng/filter compact, nút và modal xác nhận, empty/error/loading/accessibility. Logic truy vấn, phân công, mã task, UTC, quyền và ghi DB vẫn ở backend. Không thêm truy vấn DB trong Blade/JS.

Flow xác minh sau khi làm giao diện: login → workspace → tạo project → thêm workspace member vào project → tạo Task → sửa → lọc/phân trang → archive/restore. Dùng Viewer để kiểm tra đọc được nhưng không sửa, và member chưa thêm vào project phải bị 403. Không thêm test class/mocks theo chỉ đạo chủ dự án.


## Bổ sung phần 4 — hợp đồng đang chạy, thay thế giới hạn workflow ở phần 3

Phần 1–3 đã duyệt; phần 4 đã có backend/Blade và chờ duyệt. Đọc STAGE_04_TASK_WORKFLOW.md. Các ghi chú cũ “chưa có tree/status/comment/file” mô tả mốc phần 3, không còn là giới hạn của phần 4. Chưa làm phần 5.

### Trang/biến bổ sung

| View | Dữ liệu và trách nhiệm |
|---|---|
| tasks/_form | Thêm parents, task_type, parent_task_id, group_label, sort_order. Cha chỉ chọn GROUP; max 3 cấp; server kiểm tra lại cả nhánh. Giữ metadata phần 3. |
| tasks/show + tasks/workflow | task, context cũ, activities paginator 15 (actor), dependencies (predecessor), blockers (reporter/resolver), candidates, progress, statusOptions, deletable. |
| tasks/tree | workspace, project/null, tasks (assignee), nodes group theo parent_task_id hoặc root, progress, routePrefix/routeParams. |
| tasks/tree-node | Include đệ quy nhận parentId/visited, dùng nodes đã chuẩn bị; không query DB trong view. |

progress có method=estimate/count, total, done, percent, work_count. Chỉ WORK không CANCELLED; archived vẫn tính. Không tính milestone/nhóm như labor, không tự tính công thức khác ở JS. GROUP status là số liệu tổng hợp backend, không có dropdown chuyển trực tiếp.

Task new luôn có HISTORY tạo, thường phải archive thay vì xóa vật lý. UI dùng deletable + @can để ẩn nút xóa sai điều kiện; service giữ ràng buộc cuối. Metadata REVIEW/DONE/CANCELLED bị khóa. Comment/file trên DONE vẫn được phép nếu policy cho phép và project đang mở.

### Route/form thực tế

Giữ routePrefix=workspaces.projects.tasks hoặc workspaces.tasks; routeParams như phần 3. Action dưới đây append $task, rồi ID con nếu có. Dùng route(), không tự ghép URL.

| Form marker / màn hình | Route suffix / method | Fields |
|---|---|---|
| cây | .tree GET | không có; tree route đặt trước resource show |
| task-create/edit | .store POST / .update PATCH | thêm task_type, parent_task_id nullable, group_label nullable, sort_order; status/timestamps vẫn prohibited |
| task-status | .status.update PATCH | expected_status=current server status, status từ statusOptions, reason khi hủy/mở lại/reject |
| dependency-create | .dependencies.store POST | predecessor_task_id từ candidates, lag_minutes số nguyên không âm |
| dependency-delete | .dependencies.destroy DELETE | dependency UUID trên URL, không input khác |
| blocker-create | .blockers.store POST | reason |
| blocker-update | .blockers.update PATCH | status=open/resolved, reason tùy chọn để ghi chú audit; hidden status phải giữ |
| comment-create | .comments.store POST | content |
| comment-update | .comments.update PATCH | content, activity UUID trên URL |
| comment-delete | .comments.destroy DELETE | activity UUID, không gửi type/source/actor |
| attachment-create | .attachments.store POST | file; bắt buộc enctype=multipart/form-data |
| tải file | .attachments.download GET | activity UUID, dùng anchor/download stream |
| attachment-delete | .attachments.destroy DELETE | activity UUID; soft-delete và ngừng tải |
| lọc activity | .show GET | activity_type=COMMENT/HISTORY/ATTACHMENT, page |

Thành công redirect back + flash. Validation giữ old input/errors, API không trả contract JSON riêng. File không thể old() để tự chọn lại; sau lỗi người dùng cần chọn lại. Không dùng base64/file path thay multipart.

### Quyền và UX phải giữ

- statusOptions do backend tính theo actor/status. Đừng mở dropdown mọi status; GROUP không có action status. expected_status phải đi cùng form, khi báo stale thì tải lại trang.
- Hủy/mở lại/reject bắt buộc reason. Người thực hiện/task.assign mới làm bước execution; reviewer được chỉ định + task.review hoặc Owner workspace mới review/reopen DONE. Role Manager không tự là reviewer của mọi Task.
- Open blocker và FS chưa sẵn sàng sẽ trả lỗi, không toast thành công. Predecessor CANCELLED không thỏa FS. Metadata không được dùng để gửi status hoặc timestamps thay route workflow.
- Quản lý dependency cần @can link (task.assign), chỉ successor TODO; không xóa edge để lách workflow bằng quyền update thông thường. Chỉ chọn candidates backend cùng scope.
- Report blocker cần block; resolve/reopen backend còn kiểm tra tác giả/task.assign/review. Giữ @can('resolveBlocker',[$task,$blocker]) để ẩn action ngoài quyền hoặc Task đã đóng; không suy đoán từ tên role.
- Tác giả sửa comment; tác giả hoặc task.assign được xóa comment. HISTORY chỉ đọc, không hiện edit/delete; không render arbitrary HTML/content bằng {!! !!}.
- Download cần task.view hiện tại; người có quyền xem có thể đọc file người khác trong cùng phạm vi. Gỡ file cần task.attach + tác giả hoặc task.assign. Sau gỡ row bị soft-delete, bytes private còn giữ audit, không công bố đã purge file.
- Không dùng public/storage URL, Storage::url hoặc render disk/path/credentials JSON ra JS. Chỉ sử dụng download route. Không hiển thị data thô của ATTACHMENT trong feed.
- Giữ textarea id=status-reason/blocker-reason và comment-ID riêng. Các form đều @csrf/@method. Form trạng thái và blocker có field reason cùng tên nhưng ID khác nhau; không gộp form.
- Có thể chuyển feed/tree thành tabs, card, accordion, modal, thêm nhãn VN dễ hiểu; giữ native form submit làm việc khi JS tắt. Archive con trước cha, restore cha trước con. Không kéo thả gửi sang endpoint chưa tồn tại: hiện đổi parent/order qua form edit.

Kiểm tra giao diện sau thiết kế: tạo GROUP → WORK/MILESTONE con → giao người thực hiện/reviewer → FS và blocker → start/resolve/review/reject/approve → comment/file → tải với Viewer và kiểm tra ngoài scope bị chặn. Không thêm test class/mocks; dùng HTTP/browser và đọc DB thật. Routes đầy đủ xem verification/stage04/routes.json.


## Bổ sung phần 5 — hợp đồng FE đang hoạt động (17/10/2026)

Phần 1–4 đã duyệt, phần 5 đã có backend/Blade thật và chờ duyệt; chưa làm phần 6. Các dòng cũ “chưa có lịch/settings/thông báo” chỉ mô tả mốc trước phần 5. Đọc STAGE_05_CALENDAR_NOTIFICATIONS.md trước khi đổi form.

### Trang và dữ liệu controller

| Blade | Dữ liệu đã cấp | FE tiếp tục làm |
|---|---|---|
| calendar/index | workspace, range, rows (đã lọc/che riêng tư), canCreate, schedule | Bố cục tháng/tuần/danh sách, nhãn, responsive |
| calendar/_range | range: mode/timezone/start/end/days/includeCancelled | Bộ chọn ngày/range; giữ native GET |
| calendar/_row | row: id nullable, title, description nullable, owner, type, status nullable, starts_at/ends_at Carbon theo timezone xem, all_day/is_busy, task nullable, private | Card/event chip, không tạo link nếu id null |
| calendar/form | workspace,event nullable,projects,tasks,members,version,timezones | Form/modal/input; scope và selected task do server quyết định |
| calendar/show | workspace,event được phép xem,version,timezone | Trang chi tiết/sửa/hủy/xóa theo @can |
| calendar/timeline | workspace,project,range,groups,schedule | Thanh lịch theo task; group rows có offset_percent/width_percent do controller tính |
| settings/edit | settings chuẩn, schedule | Chọn language/theme/in_app và read-only giờ mặc định |
| notifications/index | notifications paginator20 | Inbox, all/unread, empty state, pagination/đánh dấu đọc |
| components/layout | preferences,unreadNotifications từ composer | Theme/lang/badge; không query DB trong FE |

Calendar rows đã qua privacy projection; tuyệt đối không nạp event gốc chỉ để tooltip/drag/drop. id=null đồng nghĩa không có link/edit/detail cho actor. Timeline chỉ có TASK_BLOCK được phép xem; không suy ra slot từ deadline/estimate, không gọi planned là actual. Task private không được lộ thông qua task link hoặc data attribute.

### Route và form

Prefix C=`workspaces.calendar` với params workspace hoặc [workspace,event]. Prefix T=`workspaces.projects.timeline` với [workspace,project]. Tất cả nằm dưới web session/auth/active.user/workspace.member; không thêm api.php/token authentication.

| Thao tác | Route name/method | data-form và trường |
|---|---|---|
| Lịch | C.index GET | calendar-range: view=month/week/list; date=Y-m-d; from/to=Y-m-d (to loại trừ); include_cancelled=0/1 |
| Tạo form | C.create GET | ?all_day=0 hoặc1 chọn kiểu input, không phải lưu dữ liệu |
| Tạo event | C.store POST | calendar-save: title,description,event_type,project_id?,task_id?,timezone,all_day,is_busy,starts_at,ends_at,visibility,status,participants[]? |
| Chi tiết/sửa form | C.show/C.edit GET | Theo @can view/update; params [workspace,event] |
| Sửa event | C.update PATCH | calendar-save: trường trên + version; giữ hidden event_type/project_id/task_id; 3 select disabled |
| Xóa | C.destroy DELETE | calendar-delete: version, CSRF, confirmation |
| Timeline | T GET | calendar-range cùng contract; thanh có tỷ lệ do server tính |
| Settings | settings.edit GET/settings.update PATCH | settings-update: language=vi/en,theme=system/light/dark,notifications[in_app]=0/1 |
| Inbox | notifications.index GET | filter=all/unread,page |
| Một thông báo | notifications.read PATCH | notification-read: unread=0/1; param UUID notification |
| Tất cả đã đọc | notifications.read-all POST | notification-read-all: CSRF |

Event enums: NOTE/MEETING/BUSY/EXTRA_AVAILABLE/TASK_BLOCK, status planned/completed/cancelled, visibility private/workspace/project. all_day=0 nhập Y-m-dTH:i theo timezone đã chọn; all_day=1 nhập Y-m-d, ngày kết thúc không gồm. Checkbox boolean có hidden0 + checkbox1, participants dùng name="participants[]" để gửi array. Bỏ tất cả lựa chọn participants nghĩa là danh sách rỗng khi save. Không gửi user_id/workspace_id/source/plan_id/integration/external_id. Chỉ TASK_BLOCK có task_id.

### Hành vi bắt buộc giữ

- Quyền calendar trong project độc lập với role workspace, nhưng còn cần active membership/workspace.view. Chủ event mới sửa/xóa event mình; @can quan trọng hơn tên role. Member mặc định không delete, hủy bằng status cancelled qua PATCH.
- Owner không có ngoại lệ mở lịch private của người khác. Chia sẻ workspace chỉ cho event không thuộc project; event project chọn private/project. participants chỉ tham khảo, không cấp quyền nội dung hay đặt chỗ người khác.
- TASK_BLOCK cho WORK chưa đóng được giao cho chính user; không GROUP/MILESTONE/all-day. Giữ 3 trường loại/project/task gốc ở form sửa. Chủ lịch có thể hủy slot cũ sau khi task đóng/giao lại nếu vẫn có quyền xem và project writable.
- Server chuẩn hóa is_busy: MEETING/BUSY/TASK_BLOCK luôn true, EXTRA_AVAILABLE false, NOTE tùy chọn. Không hiện toast thành công nếu lỗi trùng lịch. Hai lịch kề nhau được phép; cancelled không giữ chỗ. Không gửi time_entries hay đổi task.status khi sửa lịch.
- Version là token server gửi cho update/delete; không tự dựng timestamp/version ở JS. Khi báo stale cần tải lại/đối chiếu thay đổi. Không cập nhật optimistic rồi bỏ qua lỗi.
- Range tối đa93 ngày, >1.000 event ứng viên yêu cầu thu hẹp. Không đổi FE thành tải hết dữ liệu. GET range ở calendar/timeline dùng cùng timezone người xem.
- Giữ CSRF/method, old/errors, native form và xác nhận xóa. Không render description/title/body bằng raw HTML. Chưa có endpoint drag/drop/JSON; nếu làm tương tác mới phải thống nhất với backend, không tự thêm đường gọi giả.
- schedule lấy config BE, không lưu giờ mặc định trong user_settings; timezone đổi qua profile. Theme đã có hook html[data-theme], language đang là preference (nhãn hiện tiếng Việt), FE có thể hoàn thiện bộ nhãn theo language.
- Email/Telegram không có control bật hoạt động. Backend từ chối bật channel chưa cấu hình. Không truyền token/key/permission trong settings. Bật/tắt in_app ảnh hưởng thông báo mới, không xóa inbox cũ.
- Inbox và unread badge dùng cùng truy vấn phân quyền hiện tại. Người mất quyền project sẽ không thấy các thông báo cũ thuộc project. read_at khác deliveries; không thể hiển thị “email đã gửi” từ việc bấm đọc.

### Flow FE nên xác minh sau thay đổi giao diện

Tạo BUSY 09–10 → MEETING 09:30 báo lỗi → NOTE không bận cùng giờ thành công → TASK_BLOCK13–14 từ task được giao → Timeline link về event/task → mở lịch bằng actor khác thấy private busy đã che → đổi timezone hồ sơ chỉ đổi hiển thị → giao task/thêm bình luận/nộp REVIEW sinh inbox đúng người → bấm đã đọc/unread → tắt in_app không sinh thông báo mới. Dùng HTTP/browser và DB thật, không thêm test class/mocks. Dữ liệu demo và minh chứng xem verification/stage05/README.md; không chạy lại resume scripts tùy tiện.


## Bổ sung phần 6 — Dashboard/report và nền AI (17/10/2026)

Phần 1–5 đã duyệt; phần 6 có backend/Blade thật và chờ duyệt. Các ghi chú cũ “chưa có Dashboard/report/queue/AI” chỉ là mốc trước phần 6. **Chưa làm Plan/AI planning phần7**. Đọc STAGE_06_REPORT_QUEUE_AI.md trước khi nối tương tác.

### Trang và biến đã cấp

| Blade | Dữ liệu controller | FE tiếp tục |
|---|---|---|
| reports/dashboard | workspace, metrics, filters, projects, members, canCreate | Card/chart/bố cục/filter; dùng số liệu BE, không tính lại ở JS |
| reports/_filters, _hidden | filters from/to/project_id/subject_user_id/include_archived; projects/members khi có select | Input + native form, hidden filters của POST |
| reports/_metrics | metrics schema1, JSON mô tả ở flow phần6 | KPI/bảng/chart; giữ cách tính/null/phạm vi thời gian |
| reports/index | workspace, snapshots paginator20, filters ngày tạo/project | Danh sách snapshot cá nhân, pagination/empty/errors |
| reports/show, print | workspace,snapshot,metrics cố định | Chi tiết/bản in; không tự gọi live metrics để thay JSON |
| ai-runs/create | workspace,filters,projects,members,token UUID | Form prompt, cảnh báo AI chưa cấu hình, nút lưu request |
| ai-runs/index | workspace,runs paginator20,filters status/created_from/to | Danh sách/trạng thái; không tự load mọi run hệ thống |
| ai-runs/show | workspace,run,calls theo attempt | queued/running/failed/cancelled/succeeded, lỗi, làm mới/cancel/retry theo quyền |

### Routes đã có

Tất cả prefix `/workspaces/{workspace}`, name prefix `workspaces.` và web auth/session/active.user/workspace.member. Không có API/SPA/provider call từ FE.

| Name / method | Contract |
|---|---|
| dashboard GET | report-filter: from/to Y-m-d (to loại trừ), project_id?,subject_user_id?,include_archived=0/1. GET thiếu kỳ dùng tháng hiện tại theo users.timezone. |
| reports.index GET | report-list-filter: project_id?,created_from?,created_to? (loại trừ),page |
| reports.store POST | report-create: from/to/project_id?/subject_user_id?/include_archived; CSRF. Server tính và tạo snapshot. |
| reports.show GET | params [workspace,snapshot] |
| reports.print GET | params [workspace,snapshot], HTML/CSS print, dùng Ctrl+P |
| reports.export GET | params [workspace,snapshot], CSV download UTF8 BOM, private/no-store |
| ai-runs.index GET | ai-run-filter: status?,created_from?,created_to?,page |
| ai-runs.create GET | cùng filters Dashboard; cấp token UUID |
| ai-runs.store POST | ai-run-create: cùng filters + user_prompt string1..4000 + idempotency_token UUID. Throttle10/phút. |
| ai-runs.show GET | params [workspace,run], có nút làm mới; không polling bắt buộc |
| ai-runs.cancel POST | ai-run-cancel: expected_status=queued/running + CSRF |
| ai-runs.retry POST | ai-run-retry: expected_status=failed + CSRF; còn <3 calls; throttle10/phút |

Chỉ dùng các fields trên. Report/AI request không nhận metrics_json, context_snapshot, ai_commentary, actor IDs, operation/status/provider/model hay key từ browser. Redirect + flash/errors/old input là response contract. Hidden include_archived=0 + checkbox1. Không gộp GET filter và POST report thành form lồng nhau.

### Metrics và hiển thị chính xác

metrics gồm schema_version, workspace_id/name, generated_at, timezone, period {from,to,start_utc,end_utc}, filters, inventory, period_metrics, workload, sources, access, definitions, work_schedule.

- inventory.status_counts/work_count/overdue/blockers/estimate/progress là **hiện trạng lúc tạo số liệu**, không tái dựng trạng thái ở ngày cuối kỳ. Chỉ WORK; không cộng GROUP/MILESTONE hay estimate của CANCELLED.
- period_metrics.completed_work dùng completed_at trong kỳ; planned_minutes là TASK_BLOCK actor được đọc đầy đủ, actual_minutes chỉ phiên đã ended. `actual_minutes===null` phải hiển thị “chưa có dữ liệu”, không biến thành0/estimate. running_entries_excluded không cộng vào actual.
- Work thực tế đã ghi vẫn còn khi task cancelled. Thời lượng gộp khoảng chồng theo từng người, cắt theo [start,end), đơn vị **phút**, không coi con số0.05 là0.05 giờ.
- Subject filter là current assignee; không phải lịch sử ai từng được giao. Workload có thể có người tasks=0 nhưng có thời lượng do phân công đã đổi. Giữ definitions trong UI hoặc phần giải thích.
- Snapshot dùng JSON lưu cố định, có timezone/cách tính tại lúc tạo. Không sửa snapshot khi live data đổi; muốn số liệu mới → POST bản mới. Bản in/CSV cũng dùng snapshot. Chưa có chia sẻ report giữa người dùng: người tạo vẫn phải giữ quyền hiện tại trên các scope đã góp dữ liệu.
- Không fetch lịch private để dựng chart; không dùng nguồn private/busy projection phần5 thành planned chi tiết. Mất calendar/project permissions có thể làm snapshot cũ403/ẩn khỏi danh sách. Giữ policy, không giả nút có quyền từ tên role.
- sources/access phục vụ BE/audit, không dump toàn bộ metrics/source Model vào JS hoặc tooltip công khai. Backend đã lọc scope; chart chỉ lấy aggregates được cấp. Không render title/prompt/response/error bằng raw HTML.

### AI UI và giới hạn cần giữ

- AI ở phần6 là nền nhận xét report: không có planning/replanning/Plan CRUD/apply. Không tạo nút “Áp dụng kế hoạch” hoạt động giả.
- Trang create hiển thị rõ AI chưa cấu hình. Submit lưu queued; chỉ worker đổi trạng thái. Không toast “AI thành công” từ redirect queued hoặc queue log DONE. Hiện request được worker ghi failed do AI đang tắt, response/token counts null; không dựng nhận xét mẫu như kết quả thật.
- Idempotency token là UUID form do server cấp, giữ nguyên khi submit lại cùng nội dung; sửa nội dung sau khi token đã dùng cần mở form mới. Token không phải credential nhà cung cấp. Không gửi lại nhiều POST để polling; dùng GET show/Làm mới.
- Cancel/retry dùng expected_status của run; giữ errors nếu stale. Cancel running không cam kết dừng request mạng tức thì; BE bảo vệ không lưu kết quả đến muộn. Retry giữ context cũ; số liệu mới cần yêu cầu mới. Tối đa3 lượt toàn run, không chỉ mỗi job.
- @can('manage',run) và status/calls count quyết định nút; server kiểm tra lại. Chỉ requested_by có quyền report.create hiện tại được manage, không phải mọi Owner xem được run người khác.
- Provider binding hiện UnconfiguredAiProvider, AI_ENABLED false. Adapter mạng/key là việc BE ở phần sau, không phải FE thêm SDK hay gọi provider bằng JS. Không có live AI result/automatic network retry đã kiểm chứng.

Sau thiết kế: lọc Dashboard → POST snapshot → đổi task rồi so bản cũ/bản mới → print/CSV → tạo run queued → chạy worker riêng → reload thấy lỗi cấu hình → retry/cancel theo form. Dùng flow thật và DB/worker, không tạo TestCase/mocks. Minh chứng/bộ dữ liệu và giới hạn tại verification/stage06/README.md.


## Bàn giao cuối: Plan/replanning/timer/GitHub (đã có route/Blade thật)

Đọc DELIVERY_STATUS.md trước; các ghi chú “chưa có phần7” ở mục cũ là lịch sử. Tiếp tục Laravel MVC/Blade, native form + CSRF/session/redirect/errors. Không dựng một SPA/API hoặc viết lại business rules trong JS. Logic backend đã nằm ở service, FE tập trung layout, component, tree editor, validation display và responsive.

Trang | Dữ liệu server / hành động
---|---
plans/index | workspace, project, plans phân trang20; link tạo nháp, AI, impact
plans/create | workspace, project, base?, payload, people; POST workspaces.plans.store: name, project_id, base_id?, payload(JSON string), _token. Form sửa tạo phiên bản mới. Có thể thay textarea JSON bằng tree/table editor rồi serialize **đúng** docs/PLAN_PAYLOAD.md
plans/show | plan, parent?, impact; bảng payload, JSON trước/sau, actions theo quyền/trạng thái. POST workspaces.plans.action(workspace,plan,action) với expected_status và reason khi reject; action chỉ propose/approve/reject/apply. Không gửi is_active/reviewed_by/applied_at/hash từ FE
plans/impact | workspace, project, impact[{task,reasons[]}]; dùng dữ liệu thật, link mở task theo project/personal. Không tự đánh dấu hoàn thành vì hết lịch
plans/show local scheduling | POST workspaces.plans.schedule với from YYYY-MM-DD. Server tạo version local mới, không phải AI và không apply. Khi không đủ giờ/estimate/assignee hợp lệ, hiển thị errors
plans/ai | bases, token UUID; POST workspaces.planning-ai.store: operation(planning/replanning/assistance), base_id (replanning bắt buộc), project_id, user_prompt≤4000, idempotency_token. Giữ cùng token khi gửi lại; dùng form mới cho nội dung mới
ai-runs/show | tất cả operation; calls, safe error; planning/replanning có link plans chỉ khi tạo draft thành công. report giữ _metrics. assistance chỉ text. Text phải escape; không render AI HTML bằng {!! !!}
timers/index | entries của chính người dùng, started/ended; mất quyền task thì chỉ chữ “không còn quyền xem”, vẫn cho stop clock của mình. POST timers.stop(entry) không cần workspace; không đưa private task metadata vào data-* nếu mất quyền
tasks/show timer | POST workspaces.timer.start(workspace,task) khi WORK/IN_PROGRESS/assignee hiện tại. Server chặn timer thứ2 và status transition khi đang chạy. FE có thể hiển thị elapsed nhưng số liệu chính thức do server tính
integrations/github | workspace,project,connection?,logs paginate20; POST workspaces.projects.github.save: repository_id, enabled0/1, webhook_secret tùy chọn min32. Không preload secret/hash vào HTML/JS, password autocomplete=new-password; trống nghĩa giữ secret cũ. Owner integration.manage mới sửa được

Tên route thật: routes/remaining.php và routes/web.php; có thể tra docs/verification/final/routes.json. Mọi link workspace team cần project_id rõ; task cá nhân dùng scope null trong workspace personal. Chỉ ẩn nút không thay thế policy backend.

Flow nối nhau: project/show → plans.index(project_id) → create/store → show → action(propose) → action(approve) → action(apply) → task tree/calendar/timeline → dashboard/report. Nhánh edit quay lại create(base_id) và sinh version khác. Nhánh AI → AiRun show/refresh → Plan draft → cùng workflow duyệt; không có API “AI auto apply”. Khi stale/conflict, hiển thị lỗi và gợi ý tạo version từ bản mới nhất, không tự resend apply liên tục.

FE nên nâng cấp: bảng/cây soạn plan thay JSON, diff trực quan theo ref/task_id, badges status kèm chữ, modal xác nhận apply, busy/loading/empty/error states, breadcrumb workspace/project, label và lỗi cạnh từng trường, bảng cuộn trên mobile. Giữ tên form field/action/CSRF, logic duyệt và ngưỡng backend. Theme đã có settings; không phải thêm Node/Vite để chạy bản bàn giao. Screenshot functional không phải thiết kế giao diện cuối.

History Task Blade đã thêm nhãn và nội dung escape cho github.commit, timer.started/stopped, plan.applied và plan.calendar_replaced; plan activity có link phiên bản khi người xem có plan.view. FE có thể đổi bố cục nhưng phải giữ kiểm tra quyền và escaping.

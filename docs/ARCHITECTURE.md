# Kiến trúc backend — mỗi lớp một việc

Cập nhật: 06/11/2026. Tài liệu này là chuẩn hiện hành cho cấu trúc code. Các tài liệu STAGE_xx và báo cáo cũ ghi lại kiến trúc tại thời điểm đó; khi tên class/file khác với tài liệu này thì tài liệu này đúng.

Dự án vẫn là Laravel MVC + Blade (không đổi sang REST/SPA). Mục tiêu của đợt tái cấu trúc 06/11: **mỗi request chỉ kiểm quyền một lần, ở một chỗ đoán trước được; mỗi lớp chỉ làm đúng việc của nó; không lớp nào gọi ngược lên lớp trên.**

## 1. Luồng của một request

```
Route (routes/*.php)
  └─ Middleware      đăng nhập · tài khoản active · email đã xác minh · là thành viên workspace
       └─ FormRequest   chỉ validate dữ liệu đầu vào (không kiểm quyền, không gọi service)
            └─ Controller   nhận input → gọi 1 Service (ghi) hoặc 1 Query/PageData (đọc) → trả view/redirect/JSON
                 ├─ Service (ghi)      transaction + lock → kiểm quyền → nghiệp vụ → ghi DB
                 └─ Query / PageData (đọc)  kiểm quyền theo phạm vi → query → mảng dữ liệu cho view
                        └─ App\Authorization   nguồn quyền duy nhất (WorkspaceAccess, ProjectAccess, TaskAccess, …)
                               └─ Model / Eloquent  (+ IntegrityObserver kiểm bất biến cấu trúc khi lưu)
View (Blade)  chỉ hiển thị biến được truyền vào + @can(ability, model)
```

## 2. Trách nhiệm từng lớp

| Lớp | Thư mục | Được làm | Không được làm |
|---|---|---|---|
| Route | `routes/web.php`, `routes/auth.php`, `routes/workspace/*.php` | URL, tên route, middleware, ràng buộc tham số | Logic, kiểm quyền chi tiết |
| Middleware | `app/Http/Middleware` | Kiểm thô cho cả nhóm route: đăng nhập, active, verified, membership workspace | Kiểm quyền theo chức năng |
| FormRequest | `app/Http/Requests` | `rules()`, chuẩn hoá input (`prepareForValidation`), cách phản hồi lỗi validate | `authorize()`, gọi Service/DB nghiệp vụ |
| Controller | `app/Http/Controllers` | Gọi đúng 1 Service/Query, chọn view, redirect, flash, JSON. Với trang đọc **một model** được phép 1 dòng `$this->authorize('ability', $model)` | Query builder, `DB::`, transaction, tự viết điều kiện quyền, `app(...)` |
| Service (ghi) | `app/Services/<Domain>/*Service.php` | Transaction, `lockForUpdate`, `Gate::forUser($actor)->authorize(...)` trên dữ liệu đã khoá, nghiệp vụ, ghi lịch sử/thông báo | Đọc `request()`/session toàn cục, trả view |
| Query / PageData (đọc) | `app/Services/<Domain>/*Query.php`, `TaskPageData`, `CalendarViewService`, `PlanEditorData` | Kiểm quyền **theo phạm vi** (danh sách, trang có bộ lọc), query, trả mảng biến cho view | Ghi DB |
| Authorization | `app/Authorization` | Tính quyền của user trong workspace/project, các quy tắc phạm vi (task, lịch, kế hoạch, báo cáo). Cache theo request | Ghi DB, phụ thuộc Service |
| Policy | `app/Policies` | Ability trên **một model** (`view`, `update`, `review`…), dùng chung cho Service, Controller và `@can` trong Blade | Hàm helper public để lớp khác gọi trực tiếp |
| View composer | `app/View` | Dữ liệu dùng chung cho layout (menu, breadcrumbs, timer) và cờ hiển thị link module | Ghi DB |
| Model + Observer | `app/Models`, `app/Observers` | Quan hệ, cast; `IntegrityObserver` + `ReferenceIntegrity` chặn dữ liệu sai cấu trúc ở mọi lần lưu | Phân quyền |
| Support | `app/Support` | Hàm thuần không phụ thuộc DB/request: `LocalTime`, `SearchTerm`, `UiLabels`, `MetricDisplay`, `EditRevision`, `TaskFilters` | Gọi Service, Policy, DB |

Dependency chỉ đi xuống: Controller → Service/Query → Authorization → Model. Service khai báo dependency qua constructor (không `app()`), nên đọc constructor là biết lớp đó dùng gì.

## 3. Quy tắc phân quyền (quan trọng nhất)

1. **Ghi dữ liệu**: chỉ Service kiểm, bên trong transaction sau khi `lockForUpdate`. Lý do kiểm ở đây chứ không ở Request: quyền có thể bị thu hồi giữa lúc gửi form và lúc ghi; kiểm trên bản ghi đã khoá mới chắc chắn. Service cũng được Job/Artisan gọi, nên quyền phải nằm cùng nghiệp vụ.
2. **Đọc theo phạm vi** (danh sách task, kế hoạch, mẫu, lịch nhóm…): lớp Query/PageData kiểm (`assertScope`).
3. **Đọc một model** (xem task, xem báo cáo, form sửa): Controller gọi `$this->authorize('view'|'update', $model)` — một dòng, dùng Policy.
4. **Hiển thị nút/link**: Blade dùng `@can('ability', $model)` hoặc biến cờ (`$canCreateTask`, `$canViewPlans`…) do Query/Composer tính. Blade không gọi `app(...)`.
5. FormRequest không có `authorize()`. Middleware `workspace.member` chỉ đảm bảo là thành viên đang hoạt động.

Nguồn quyền:

| Class | Dùng cho |
|---|---|
| `WorkspaceAccess` | membership, permissions, `allows`, `visible($user)` (workspace được xem), `assignableRoles`, chặn cấp quyền vượt quyền mình |
| `ProjectAccess` | permissions trong project, `visible` (project được xem), `taskProjectIds`, `eligibleMembers` |
| `TaskAccess` | `scope` (quyền task trong workspace/project), `projectWritable`, `ancestorsWritable`, `mutable`, `templates` |
| `CalendarAccess` | `scope` cho lịch |
| `PlanAccess` | `allows`, `writable`, `run` (xem/thao tác AI run kế hoạch) |
| `ReportAccess` | `scope`, `projects`, `manifest` (kiểm lại phạm vi của snapshot/AI run báo cáo) |

`WorkspaceAccess` và `ProjectAccess` đăng ký `scoped` (một instance mỗi request/job) và **ghi nhớ kết quả** membership/permissions trong request. Bên trong `DB::transaction` luôn đọc lại DB, nên việc kiểm lại quyền dưới lock không bị cache làm sai. Khi `User`, `Workspace`, `WorkspaceMember`, `Role`, `Project`, `ProjectMember` được lưu/xoá, cache được xoá (`AppServiceProvider::registerModelHooks`).

## 4. Bản đồ thư mục

```
app/
  Authorization/   WorkspaceAccess ProjectAccess TaskAccess CalendarAccess PlanAccess ReportAccess
  Http/
    Controllers/   mỏng; Concerns/ScopesTaskRoutes (route task có/không project)
    Middleware/    EnsureUserIsActive EnsureWorkspaceMembership RequireVerifiedAccount RequireFullFeatureSchema SecurityHeaders …
    Requests/      *Request (validate) ; Task/ (TaskRequest, TaskStatusRequest, TaskCommentRequest, TaskBlockerRequest,
                   TaskDependencyRequest, TaskAttachmentRequest, BulkTaskRequest, SavedFilterRequest)
  Jobs/  Mail/  Models/  Observers/ (IntegrityObserver, ReferenceIntegrity)  Policies/
  Services/
    Account/       AccountService (hồ sơ, mật khẩu, reset) · RegistrationService · AccountMailService
    Ai/            AiRunService · PlanningContext · Providers/
    Calendar/      CalendarService (ghi) · CalendarViewService (đọc) · TeamCalendarService (ghi)
    Integration/   GithubSettingsService · GithubWebhookService
    Notification/  NotificationService
    Planning/      PlanService (ghi) · PlanQuery (đọc) · PlanReviewService · PlanScheduleService · PlanEditorData
    Project/       ProjectService (ghi) · ProjectQuery (đọc)
    Queue/         QueueObservation
    Report/        ReportService (tính số liệu, snapshot) · ReportQuery (lịch sử, dashboard, CSV)
    Search/        SearchService
    Setting/       SettingService
    Task/          TaskService · TaskWorkflowService · TaskUtilityService · TaskTemplateService (ghi)
                   TaskPageData · TaskTemplateQuery (đọc) · TaskParticipants · TaskTreeService · TaskHistory
                   TaskReminderService · TaskEditConflict
    TimeTracking/  TimeTrackingService
    Workspace/     WorkspaceService · InvitationService (ghi) · WorkspaceQuery (đọc)
  Support/         LocalTime SearchTerm UiLabels MetricDisplay EditRevision TaskFilters
  View/            AppShell · Composers/LayoutComposer · Composers/ScopeLinksComposer
routes/
  web.php          công khai, đăng nhập, trang cấp tài khoản, nhóm /workspaces/{workspace}
  auth.php         quên/đặt lại mật khẩu, xác minh email
  workspace/       workspace.php tasks.php projects.php calendar.php planning.php reports.php
```

Quy ước tên: `XxxService` = thao tác ghi; `XxxQuery`/`XxxPageData`/`CalendarViewService` = dữ liệu cho màn hình.

## 5. Ví dụ luồng

**Tạo task trong project** — `POST /workspaces/{w}/projects/{p}/tasks`
1. Middleware: đăng nhập, active, verified, là thành viên `{w}`.
2. `Task\TaskRequest`: validate title, priority, deadline…
3. `TaskController@store` → `TaskService::save()`.
4. `TaskService::save`: transaction, khoá workspace/project, `TaskAccess::projectWritable` + `scope(task.create)`; nếu đổi người làm/duyệt thì cần `task.assign`; ghi task, `TaskHistory` (→ `NotificationService`), đồng bộ cây.
5. Controller redirect tới trang task.

Kiểm quyền: **1 lần** (bước 4). Trước đây: middleware + `TaskRequest::authorize` + `TaskController::context` + `TaskService` (3 lần kiểm cùng một quyền, kèm query danh sách thành viên chỉ để lấy tên route).

**Danh sách task** — `GET …/tasks?view=board`
`TaskController@index` → `TaskFilters::clean` (validate query string) → `TaskPageData::board()` (assertScope `task.view`, query, phân trang) → view.

**Mời thành viên** — `POST /workspaces/{w}/invitations`
`InvitationRequest` (email, role thuộc workspace) → `InvitationController@store` → `InvitationService::issue` (khoá, `manageMembers`, không cho cấp Owner/vượt quyền, ghi lời mời, gửi mail).

## 6. Thêm chức năng mới — checklist

1. Route trong file domain tương ứng ở `routes/workspace/` (hoặc `web.php` nếu cấp tài khoản).
2. FormRequest chỉ có `rules()`.
3. Ghi dữ liệu: method mới trong Service, transaction + lock + `Gate::forUser($actor)->authorize()` hoặc `abort_unless($this->tasks->scope(...))`.
4. Màn hình đọc: method trong `XxxQuery`/PageData, trả mảng cho view; kiểm phạm vi ở đó.
5. Quy tắc quyền mới: ability trong Policy (theo model) hoặc hàm trong `App\Authorization` (theo phạm vi). Không thêm hàm helper vào Policy để Service gọi.
6. Blade: chỉ dùng biến được truyền và `@can`.
7. Kiểm tra bằng flow thật (xem `docs/verification/architecture-2026-11-06/`).

## 7. Ngoại lệ có chủ đích

- `TaskController@resolveEdit` gọi `$this->authorize('update', $task)` dù sau đó Service cũng kiểm: nhánh lỗi của màn hình đối chiếu render dữ liệu task **trước khi** tới Service.
- `PlanController@preview` kiểm quyền xem kế hoạch, còn `PlanReviewService::preview` kiểm `plan.apply`+`plan.approve`: hai quyền khác nhau, không trùng.
- `User::sendEmailVerificationNotification` và `ProcessAiRun::failed` vẫn dùng `app()`: đây là hook của framework, không nhận dependency injection.
- `TaskEditConflict` nhận `Request` để lưu bản nháp đối chiếu trong session; `CalendarViewService::range/presentation` nhận `Request` để đọc tham số lịch. Request được truyền tường minh, không dùng `request()` toàn cục.
- `RequireFullFeatureSchema` giữ nguyên (trang 503 khi chưa chạy migration 23/10).

## 8. Thay đổi ngày 06/11/2026 (tên cũ → mới)

| Trước | Sau |
|---|---|
| `App\Support\WorkspaceAccess`, `ProjectAccess`, `ReportAccess` | `App\Authorization\…` (thêm cache theo request) |
| `TaskPolicy::scope/projectWritable/mutable` (Service, Request, Support, View gọi trực tiếp) | `App\Authorization\TaskAccess`; `TaskPolicy` chỉ còn ability |
| `CalendarEventPolicy::scope` | `App\Authorization\CalendarAccess::scope` |
| `PlanService::allowed`, `PlanningContext::allowed` | `App\Authorization\PlanAccess::allows/run` |
| `TaskTemplateService::allowed`, `TeamCalendarService::allowed` | `TaskAccess::templates`, `CalendarAccess::scope` |
| `TimeTrackingService::allowed` | `TimeEntryPolicy::adjust` (`@can('adjust', $entry)`) |
| `authorize()` trong 16 FormRequest | bỏ; quyền nằm ở Service/Query |
| `TaskActionRequest` (rẽ nhánh theo tên route) | `Task\TaskStatusRequest`, `TaskDependencyRequest`, `TaskBlockerRequest`, `TaskCommentRequest`, `TaskAttachmentRequest` |
| Query trong Controller (Task, Project, Workspace, Report, AiRun, Plan, Calendar, Template, Search, Notification, Reminder, Time) | `TaskPageData`, `ProjectQuery`, `WorkspaceQuery`, `ReportQuery`, `PlanQuery`, `CalendarViewService`, `TaskTemplateQuery`, `SearchService`, `NotificationService::inbox`, `TaskReminderService::visibleFor`, `TimeTrackingService::entries/adjustments` |
| Transaction trong `ProfileController`, `PasswordRecoveryController`, `GithubIntegrationController` | `AccountService`, `GithubSettingsService` |
| `TaskUtilityService::people/mentions` ↔ `TaskWorkflowService` (gọi vòng qua `app()`) | `TaskParticipants` |
| `CalendarService::localTime` (6 Service phụ thuộc CalendarService chỉ vì hàm này) | `App\Support\LocalTime::parse` |
| `TaskFilters::applyPreset` (Support gọi Policy) | `TaskPageData` (private) |
| `ReportService::defaults` gọi từ `ReportRequest` | tính ngay trong `ReportRequest` |
| `PlanEditorData` đọc `request()` toàn cục | nhận `$sourceQuery`, `$sourceSelected` |
| `App\Support\AppShell`, composer trong `AppServiceProvider` | `App\View\AppShell`, `App\View\Composers\*` |
| `App\Services\Observers`, `App\Services\Mail`, `App\Support\ReferenceIntegrity` | `App\Observers`, `App\Mail`, `App\Observers\ReferenceIntegrity` |
| Blade gọi `app(PlanService)`, `app(TaskPolicy)`, `app(ProjectAccess)`, `app(TimeTrackingService)` | biến `$canViewPlans`, `$canCreatePlan`, `$canCreateTask`, `$canReviewTasks`, `$canScheduleTask`, `$canViewIntegration`, `$canManage`, `@can('adjust')` |
| `routes/stage05.php`, `stage06.php`, `remaining.php`, `full_features.php`, `task_utilities.php`, `task_workflow.php`, `calendar.php`, `account_security.php` | `routes/auth.php`, `routes/workspace/{workspace,tasks,projects,calendar,planning,reports}.php` |
| `account_security.php` bị require hai lần → 7 route rác `workspaces.password.*`, `workspaces.verification.*` | require một lần; 7 route rác bị loại |

Số liệu (đo bằng grep trên mã nguồn trước/sau):

| Chỉ số | Trước | Sau |
|---|---|---|
| `app(X::class)` trong `app/` | 152 | 2 (hook framework) |
| Lời gọi query builder trong Controller | 120 | 0 |
| FormRequest có `authorize()` | 16 | 0 |
| Chỗ gọi Policy như helper | 61 | 0 |
| Blade gọi `app(Service/Policy)` | 13 | 0 |
| Tổng dòng Controller / `TaskController` | 2000 / 303 | 1608 / 215 |
| Thời gian crawl 1.548 trang GET (review DB) | 107,8 s | 60–63 s |

Tên route, URL, form Blade, payload, CSRF, JSON panel và hành vi phân quyền giữ nguyên — xem minh chứng tại [verification/architecture-2026-11-06](verification/architecture-2026-11-06/README.md).

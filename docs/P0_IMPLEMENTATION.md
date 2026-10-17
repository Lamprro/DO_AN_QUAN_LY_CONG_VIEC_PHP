# Triển khai P0 — Laravel MVC / Blade

Cập nhật 17/10/2026. Đây là hiện trạng sau vòng P0, đọc trước backlog UI_UX_FUNCTIONAL_IMPROVEMENTS.md. Các mục P1/P2 vẫn là đề xuất. Logic nghiệp vụ tiếp tục ở Request/Policy/Service/Eloquent; Blade nhận dữ liệu đã được kiểm quyền. Không thêm API/SPA, TestCase, mock hay lớp kiểm thử.

## 1. Phạm vi đã làm

| Nhóm P0 | Kết quả / nơi sửa |
|---|---|
| Khung ứng dụng | AppShell::build và layout: workspace picker, sidebar theo quyền, scope project, breadcrumb, tài khoản, thông báo, menu mobile |
| Giao diện dùng chung | p0-ui.css: token light/dark/system, card/badge, focus rõ, button, grid, form, bảng task/project thành thẻ trên màn hình hẹp, reduced motion/print |
| Form | app-ui.js: lỗi summary có link tới field, inline/aria-invalid, mở vùng lỗi bị thu, old input, hiện mật khẩu, chống gửi lặp, trạng thái đang gửi, cảnh báo rời form chưa lưu |
| Auth/profile | Giữ form native/CSRF, autocomplete hiện có; hai form hồ sơ/mật khẩu độc lập; tìm múi giờ và ví dụ giờ hiện tại theo timezone đã chọn |
| Workspace/member/role | Vai trò/trạng thái tiếng Việt, nhóm permission theo module; chuyển Owner xác nhận người nhận/tác động; rời/xóa riêng; counts workspace chỉ tính project/task được xem |
| Project/member | Card/table trong URL view=cards/table, giữ lọc/sort/page; breadcrumb cha được xem; thông báo lưu trữ/đóng project; người trong workspace và quyền project vẫn độc lập |
| Task list/tree | Nhãn tiếng Việt, thông tin bộ lọc và xóa lọc, bảng mobile có nhãn cột; GROUP mở/thu bằng details; phân trang/query server giữ nguyên |
| Task detail/workflow | Hành động riêng Bắt đầu/Nộp duyệt/Duyệt/Yêu cầu sửa/Hủy/Mở lại, lý do disabled từ cùng guard backend; GROUP giải thích trạng thái tổng hợp |
| Blocker/dependency/file/history | Link predecessor, FS/lag rõ; người/thời điểm blocker; tệp tên/kích thước trước gửi, giới hạn thật; history before/after theo tên và nhãn, private download giữ nguyên |
| Timer | Thanh đang làm toàn cục, thời gian từ started_at thật; người mất quyền không thấy task metadata/link nhưng có thể dừng giờ của mình |
| F01 | revision tăng dần cho Task/Project, kiểm expected_revision dưới transaction; lỗi stale giữ bản nhập, không ghi đè |
| F03 | Editor plan thêm loại/cha/người làm/reviewer/estimate, FS/lag/slot, ref ổn định, lên/xuống, cảnh báo tham chiếu/cycle/depth; source picker tìm theo mã/tên trên web GET có auth |
| F02 | Diff người dùng theo task_id/lineage/ref và thứ tự; preview dùng chính đường apply, rollback mọi thay đổi trước trả trang; apply thật luôn kiểm lại |
| AI P0 | Nhãn trạng thái dễ hiểu, tách draft/approved/applied/active, queued/running không giả thành công; cờ AI/integration và key không đổi |
| Bàn giao | Hợp đồng mới tại đầu FRONTEND_HANDOFF.md, hướng dẫn vận hành/migration, script và bằng chứng tại verification/p0 |

Các tiện ích P1/P2 như preset công việc nâng cao, Kanban, kéo thả Calendar, autosave server, quên mật khẩu/email verification, phần trăm AI và rich text chưa được thêm. Bản này cho FE nền chức năng và component để tiếp tục làm giao diện.

## 2. Flow qua các hàm

### 2.1. Chống ghi đè Task/Project

GET edit → Controller lấy model và quyền → Blade hidden expected_revision từ model (hoặc old input khi lỗi) → PUT/PATCH cùng route hiện có → TaskRequest/ProjectRequest kiểm integer bắt buộc khi sửa → TaskService::save/ProjectService::save khóa workspace/model và kiểm quyền → EditRevision::check so revision đã gửi với bản hiện tại → model update qua trait HasEditRevision tăng revision → history nghiệp vụ hiện có → redirect show.

Tab A lưu revision1 thành2; tab B vẫn gửi1 bị ValidationException. Dữ liệu DB của A được giữ, nội dung B còn trên form để đối chiếu. Link ?fresh=1 bỏ old input có chủ đích để tải bản mới. Không tự merge và không dùng updated_at làm token. Workflow/archive thay model cũng tăng revision, nên form metadata mở trước thay đổi này phải tải lại. Client không được tự ghi revision qua mass assignment.

### 2.2. Workflow và timer

TaskController::show → TaskWorkflowService::availability → kiểm TaskPolicy execute/review, tạo actions {status,label,enabled,reason,needs_reason}. blockingReason kiểm blocker, predecessor DONE+completed_at+lag, reviewer, successor đã chạy và timer. POST status vẫn gọi TaskWorkflowService::status → locked → authorize → expected_status → graph → reason → **cùng blockingReason** → update/history/tree sync. Nút disabled chỉ giúp người dùng hiểu trước; server vẫn từ chối request giả.

Button name=status/value=enum là một phần contract. app-ui.js giữ giá trị submitter bằng hidden trước khi khóa nút, tránh mất status khi native submit. pageshow dọn hidden tạm và phục hồi trạng thái disabled gốc, không bật lại nút bị nghiệp vụ chặn.

Layout composer → AppShell::build → TimeEntry đang chạy của chính actor → TaskPolicy::view quyết định được hiển thị task/link hay nhãn chung. POST timers.stop → TimeTrackingService::stop chỉ chủ entry được dừng, kể cả khi membership mất hiệu lực. Không đổi began/ended timestamps để giả giờ làm. Đồng hồ trình bày dựa trên started_at; kết quả chính thức nằm ở ended_at và tính toán server.

### 2.3. Soạn kế hoạch

PlanController::create → scope/base được kiểm quyền → PlanEditorData::build cấp payload, eligible users/reviewers, source task đúng scope, giới hạn → Blade editor-task/create → plan-editor.js thao tác DOM và ref ổn định → native POST PlanController::store → parse/normalize → PlanService::validate/create/fingerprint → lưu **draft**, redirect show. Chưa tạo task/lịch lúc lưu nháp.

Source search dùng GET cùng trang create với source_q, project_id/base_id và source_selected[]. JS đọc lại HTML #plan-editor-data, không tạo endpoint JSON/API mới. Tối đa200 kết quả cơ sở/lần, cộng tối đa100 nguồn đã chọn và các nhóm cha tối đa3 vòng; tìm cụ thể hơn khi còn kết quả. Query source thêm kiểm task.view, tránh lộ metadata cho custom role chỉ có quyền plan. apply vẫn kiểm lại task.update/assign và trạng thái nguồn. Không xem một select disabled là phân quyền.

Source được chọn tự điền các field và thêm nhóm cha nếu hợp lệ. Sau tìm kiếm, cả dòng có sẵn và dòng thêm mới đều dùng tập kết quả hiện tại. Đổi tiêu đề/thứ tự không đổi ref. Bỏ dòng đang được dùng bởi cha/FS/slot bị chặn cho đến khi xử lý tham chiếu. Row order được ghi thành sort_order khi apply; không đổi ID công việc hiện hữu.

Lỗi backend tasks.N.field được ánh xạ thành editor.tasks.N.field. Sau redirect, DOM dựng theo thứ tự payload đã gửi, link lỗi trỏ đúng dòng mới dựng. Bản payload sai cấu trúc không làm trang lỗi500: giữ vùng JSON nâng cao để sửa, không âm thầm bỏ dữ liệu rồi báo thành công.

### 2.4. Diff, preview và apply

PlanController::show → PlanReviewService::diff → so với parent theo task_id, applied_task_ids của lineage rồi ref → hiển thị thêm/đổi/giữ/bỏ khỏi đề xuất, các trường trước/sau, dependency và slot. Bỏ khỏi đề xuất **không xóa task/dependency đã lưu**. Diff với parent khác với preview đối chiếu DB hiện tại.

GET preview → PlanController::preview/bound → PlanReviewService::preview kiểm cả plan.apply và plan.approve → mở transaction ngoài, khóa actor/workspace/plan, kiểm trạng thái lại → chỉ trong transaction mô phỏng chuyển draft/proposed thành approved → gọi chính PlanService::transition(apply) → TaskService/TaskWorkflowService/CalendarService áp dụng thật trong transaction lồng → dựng DTO tên/giá trị tác động → **finally rollback về transaction level trước đó**, kể cả khi validation hoặc exception.

Vì vậy preview có SQL ghi tạm trong transaction, nhưng **không lưu thay đổi nghiệp vụ bền vững**. Đây không phải một hàm chỉ SELECT. Không gọi provider, gửi email, ghi file, queue hay side effect ngoài DB trên đường này. Nếu sau này thêm afterCommit/observer/event ngoài DB vào apply thì phải rà soát preview trước khi ghép. SQLite có thể khóa ghi ngắn trong lúc preview; không dùng nó như cache đảm bảo hiệu lực cho lần apply sau.

DTO có valid/errors/tasks(action,ref,title,changes)/events/replaced/base_active_label. Trang approved+valid mới có form apply. Native POST apply vẫn gọi PlanService::transition với expected_status=approved, kiểm snapshot, current rights, base active, chỉ TODO chưa bắt đầu, membership, FS, slot conflict, rồi transaction commit. applied_at bảo vệ replay không nhân đôi. Lỗi ở slot sau khi đã tạo task vẫn rollback toàn bộ.

## 3. Database và triển khai

- Migration mới 2026_10_17_000020_add_edit_revisions thêm tasks.revision và projects.revision mặc định1. Đã chạy migrate --force trên DB hiện tại; không reset/migrate:fresh, không seed đè dữ liệu chính.
- Bản form cũ mở trước migration không gửi expected_revision sẽ bị từ chối; tải lại form.
- Snapshot plan tạo trước migration có thể stale do fingerprint Task/Project nay có cột revision. Tạo phiên bản mới và rà soát; không sửa hash cũ hoặc bỏ guard. Plan đã apply và lịch sử giữ nguyên.
- Asset dùng file public trực tiếp, không cần thêm framework/npm build. Chạy migrate, clear/rebuild cache bằng môi trường PHP/Composer hiện có khi cập nhật.
- .env, APP_KEY, AI_ENABLED, INTEGRATIONS_ENABLED được giữ nguyên. Không đưa database live, cookies, key hoặc tệp riêng vào gói source.

## 4. Kiểm chứng thực tế

Đọc verification/p0/README.md, http-flow.json, boundary-flow.json và browser-readback.json. Đây là các quan sát HTTP thật với session/CSRF/form render và SQL read-only sau mỗi hành động; không dựa vào lớp test/mocks.

Đã kiểm hai tab Project/Task stale; lỗi field và payload sai; draft-only; preview draft/approved không đổi count/SHA của tasks/projects/plans/dependencies/calendar/activity/notifications/time_entries; áp dụng và gửi lại; revision dùng UUID cũ; blocker/timer/FS/DONE; Viewer/outsider; native hidden enum; advanced JSON; source search và nhóm cha; conflict muộn rollback; snapshot stale; workspace count không tiết lộ project ẩn. Browser đã tạo task tree/FS/slot, đổi tên/thứ tự giữ ref, native status button, cây3 tầng, tìm nguồn giữ bản nhập, source picker dòng mới, menu Escape trả focus.

Giới hạn bằng chứng: chạy PHP8.2/SQLite trên máy này; chưa xác nhận MySQL/MariaDB/production, screen reader chuyên dụng, Safari, màn hình desktop vật lý rộng hay load test lớn. Ảnh mobile từ viewport IAB khoảng319px, không phải giả lập thiết bị. Các cờ dịch vụ ngoài vẫn tắt, không tuyên bố provider AI/GitHub network đã thành công. Một kịch bản bổ sung tạo/gán custom role mới không chạy do auto-review từ chối thay đổi quyền; kiểm quyền hiện có, SQL read-back và kiểm source guard trong mã đã thực hiện. Không cần cấp thêm quyền để dùng các thay đổi P0.

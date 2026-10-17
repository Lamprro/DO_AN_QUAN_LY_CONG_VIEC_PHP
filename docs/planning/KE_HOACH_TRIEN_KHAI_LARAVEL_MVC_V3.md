# Kế hoạch triển khai hệ thống quản lý công việc — Laravel MVC / Blade

Ngày lập: 17/10/2026. Bản kế hoạch để chủ dự án xem xét và quyết định triển khai.

> Cập nhật khi triển khai: chủ dự án yêu cầu chỉ xử lý backend/database, giao FE cho AI khác; làm từng phần và dừng duyệt flow sau mỗi phần. Xác minh trực tiếp bằng luồng thật và dữ liệu trước/sau, không thêm các lớp test để thay cho kiểm tra thực tế. Những mô tả kiểm thử tự động trong kế hoạch gốc dưới đây được điều chỉnh theo chỉ đạo mới này. Tiến độ và bằng chứng thực tế nằm tại docs/IMPLEMENTATION_PROGRESS.md, docs/verification/README.md trong repository Laravel.

## 1. Căn cứ, phạm vi và giả định

- Bốn ảnh tiêu chí chấm điểm do chủ dự án gửi: phân tích yêu cầu; thiết kế; chức năng; kiểm thử và bảo mật; triển khai; làm việc nhóm; trình bày/bảo vệ.
- ERD hiện tại mang tên **V3 — 20 bảng**, có **61 quan hệ khóa ngoại**. Báo cáo trong chat khác cũng đã chuyển sang giải thích 20 bảng. Kế hoạch này lấy V3 làm cơ sở; các mô tả V2 25 bảng trước đây là tài liệu lịch sử.
- Mã nguồn tại `D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP` hiện là bộ khung Laravel 12, route welcome, model User và migrations mặc định. Kiểm tra local thấy HEAD `45d222d`; chat khác báo đã push báo cáo ở commit `fbe6bb4`, nên trước khi code cần fetch và đối chiếu nhánh remote, không giả định bản local đã chứa báo cáo mới.
- Định hướng kiến trúc theo yêu cầu mới: Laravel MVC, Eloquent Model, Controller, Blade View; đăng nhập bằng session. Luồng người dùng dùng web routes, không cần xây một hệ thống REST API riêng.
- Nhóm 5 người theo báo cáo đã trao đổi; tạm gọi TV1–TV5 để chủ dự án phân tên. Lịch đề xuất 10 tuần từ khi duyệt bắt đầu; chưa áp lịch ngày vì chưa biết hạn nộp và quỹ giờ thực tế.
- Giả định mỗi người có 10–14 giờ/tuần: tổng khoảng 500–700 giờ người cho cả dự án, gồm khoảng 15–20% dự phòng. Cần điều chỉnh theo năng lực và lịch học của nhóm.
- Kế hoạch bao gồm việc viết chức năng, kiểm thử và chuẩn bị minh chứng sau khi được chủ dự án giao triển khai. Lần lập kế hoạch này chỉ tạo tài liệu.

## 2. Sản phẩm đích và thứ tự ưu tiên

### 2.1. Bản nền bắt buộc trước AI

Một người có thể đăng ký/đăng nhập, tạo workspace cá nhân/nhóm, mời thành viên, phân vai trò, tạo project và task/sub-task, phân công người thực hiện, theo dõi trạng thái, tìm kiếm/lọc/sắp xếp/phân trang, trao đổi trên task, xem lịch và dashboard.

Đây là luồng phải chạy xuyên suốt trước khi tích hợp AI. Mọi thao tác quan trọng phải kiểm tra quyền, dữ liệu đầu vào và hiển thị thông báo lỗi rõ ràng.

### 2.2. Tính năng thể hiện điểm riêng của đồ án

AI nhận yêu cầu → tạo bản kế hoạch nháp → người dùng xem và chỉnh sửa → người có quyền xác nhận → cập nhật cây task và lịch → giữ lại phiên bản trước cùng lịch sử thay đổi. Có thêm đề xuất điều chỉnh khi task bị trễ/bị chặn và chức năng hỏi hỗ trợ xử lý blocker.

Calendar/Timeline thể hiện các sự kiện, khoảng bận và khung làm task. Dashboard/báo cáo có số liệu tính bởi backend; AI chỉ bổ sung nhận xét.

### 2.3. Mở rộng có thứ tự

Ưu tiên sau khi lõi đạt nghiệm thu: ghi sự kiện GitHub; email tổng hợp/cảnh báo; time entry thực tế; Google Calendar và Telegram. Khi hạn nộp gần, cắt giảm mở rộng trước, giữ thời gian kiểm thử và hồ sơ chấm điểm.

ERD V3 vẫn là sơ đồ đích 20 bảng. Những bảng đánh dấu EXT có thể tạo cấu trúc trước nhưng chỉ bật chức năng khi đến đợt tương ứng; không làm giả tích hợp chưa hoàn thành.

## 3. Kiến trúc MVC và luồng xử lý

Luồng hiển thị: trình duyệt → route GET → middleware đăng nhập → policy kiểm tra quyền → controller truy vấn model → view Blade → HTML.

Luồng ghi: form Blade → POST/PATCH/DELETE có CSRF → middleware → Form Request kiểm tra dữ liệu và quyền → controller → xử lý nghiệp vụ/model trong transaction khi cần → redirect về trang kết quả kèm flash message. Lỗi validation quay lại form, giữ dữ liệu đã nhập và chỉ rõ trường lỗi.

| Thành phần | Trách nhiệm | Ví dụ |
|---|---|---|
| Model/Eloquent relationship | Biểu diễn bảng, quan hệ, casts và scope truy vấn | Task thuộc workspace/project; task có con, dependency và activity. |
| View/Blade | Hiển thị, layout, form, lỗi, trạng thái rỗng | `tasks/index`, `tasks/show`, `tasks/_form`. |
| Controller | Nhận request, gọi policy/nghiệp vụ, chọn view/redirect | TaskController có index/create/store/show/edit/update/destroy. |
| Form Request | Kiểm tra đầu vào theo từng thao tác | StoreTaskRequest, UpdateTaskRequest. |
| Policy và middleware | Quyết định ai được làm gì và truy cập tài nguyên nào | TaskPolicy, ProjectPolicy, active workspace middleware. |
| Service/Action | Xử lý nghiệp vụ có nhiều bước, dùng lại được | Chấp nhận lời mời, đổi trạng thái task, áp dụng plan. |
| Job | Xử lý lâu hoặc cần retry ở nền | GeneratePlanJob, SendDigestJob. |

Service/Job bổ trợ MVC. Controller vẫn là nơi tiếp nhận thao tác từ web; không đưa truy vấn, xử lý AI hay kiểm tra quyền vào Blade.

Không tạo token xác thực API cho luồng Blade thông thường. Nếu calendar cần kéo thả hoặc cập nhật nhanh, có thể dùng request cùng domain được bảo vệ session/CSRF; MVP dùng form và trang HTML trước. MVC vẫn cho phép gọi API của nhà cung cấp AI từ server hoặc nhận webhook GitHub cho phần mở rộng; đây là tích hợp bên ngoài.

### 3.1. Cấu trúc thư mục dự kiến

```text
app/
  Models/                 # Model cho 20 bảng
  Http/Controllers/       # Auth, Workspace, Project, Task, Calendar, Plan, Report...
  Http/Requests/          # Validate form
  Http/Middleware/        # Kiểm tra workspace/membership hoạt động
  Policies/               # Quyền truy cập từng tài nguyên
  Services/               # Workspace, Task, Calendar, Planning, Reporting...
  Jobs/                   # AI, email, xử lý webhook
  Enums/                  # Trạng thái và loại dữ liệu dùng chung
resources/views/
  layouts/ components/ auth/ dashboard/
  workspaces/ roles/ projects/ tasks/ calendar/
  plans/ reports/ notifications/ settings/ errors/
routes/web.php
routes/console.php
config/permissions.php
config/work_schedule.php
database/migrations/ factories/ seeders/
tests/Feature/ tests/Unit/
docs/requirements/ docs/design/ docs/testing/ docs/team/ docs/deployment/
```

Tách thêm file route theo module chỉ khi cần, nhưng nạp chúng dưới nhóm middleware web/auth nhất quán. Không tạo lớp repository cho mọi model nếu chỉ bọc lại Eloquent đơn giản.

### 3.2. Công nghệ đề xuất

| Phần | Lựa chọn đề xuất | Việc cần làm |
|---|---|---|
| Backend | Giữ Laravel 12 / PHP hiện tại ở bước đầu | Kiểm tra yêu cầu môi trường của các package trước khi thêm. |
| Giao diện | Blade + Tailwind/Vite đang có | Layout thống nhất; JavaScript chỉ cho thao tác cần tương tác. |
| Database | MySQL/MariaDB cho bản nhóm, nếu phù hợp môi trường nộp | Chốt phiên bản dùng chung; tạo database mới cho đồ án, không dùng dữ liệu dự án khác. |
| Redis | Lời mời có TTL và khóa chấp nhận lời mời | Chọn cách chạy Redis trên máy nhóm; driver PHP hoặc Predis tùy môi trường. |
| Queue | Database queue trước để dễ chạy demo | Có worker riêng; Redis queue là tùy chọn vận hành sau. |
| File | Storage private cho tệp task | Tải qua controller có kiểm tra quyền, không lộ đường dẫn lưu trữ. |
| AI | Lớp adapter gọi từ PHP server | Nhà cung cấp/model do chủ dự án chọn; khóa ở cấu hình môi trường. |
| Git | Giữ repository hiện có, nhánh tính năng và PR | Đồng bộ remote trước khi làm; không ghi đè báo cáo hoặc commit từ chat khác. |

Đây là phương án để lập kế hoạch, chưa phải các lựa chọn công nghệ đã được chủ dự án duyệt hết.

## 4. Đối chiếu đầy đủ tiêu chí trong 4 ảnh

| Mã | Yêu cầu chấm điểm | Công việc và minh chứng phải có |
|---|---|---|
| P1-01 | Xác định người dùng, vai trò, dữ liệu, quy trình, phạm vi/ràng buộc | Tài liệu yêu cầu; ma trận quyền; use case; luồng chính/ngoại lệ; danh sách trong/ngoài MVP. |
| P2-01 | Thiết kế CSDL, ERD, quan hệ hợp lý | ERD V3 đã chốt; từ điển 20 bảng; FK/unique/index; chính sách xóa và phạm vi workspace. |
| P2-02 | Migration, model, relationship chuẩn Laravel | Migrations có thứ tự; Eloquent relations; casts; factory/seeder; kiểm tra tạo DB từ môi trường trống. |
| P2-03 | Phân quyền và vai trò | Roles JSON + permission catalog; policy/middleware; tình huống không có quyền/cross-workspace. |
| P2-04 | Route/controller/view rõ ràng | Danh mục web route; controller theo module; Blade layout/components; sơ đồ thư mục và luồng request. |
| P2-05 | Use case, ERD, sơ đồ chức năng | Bộ sơ đồ và thuyết minh; mỗi chức năng chỉ rõ actor và tài nguyên liên quan. |
| P3-01 | Hoàn thành CRUD chính | Workspace, role, project, task, sự kiện lịch; màn hình danh sách/chi tiết/form; dữ liệu được lưu thật. |
| P3-02 | Tìm kiếm/lọc/sắp xếp/phân trang | Task/project lists có query parameters; giữ bộ lọc qua trang; whitelist cột sort; kiểm thử không lộ dữ liệu ngoài quyền. |
| P3-03 | Áp dụng MVC: route/controller/model/Blade | Demo đi từ form Blade đến controller, Form Request, Model và redirect/view. |
| P3-04 | Validation form | Form Request; thông báo tiếng Việt; giới hạn độ dài, uniqueness, kiểu file/ngày/quan hệ. |
| P3-05 | Đăng nhập/đăng xuất/xác thực | Đăng ký, login, logout, session; middleware auth; dữ liệu demo nhiều tài khoản. |
| P3-06 | Blade dễ dùng, layout phù hợp | Sidebar/header/breadcrumb; form thống nhất; empty/error/loading states; hiển thị trên desktop và màn hình nhỏ. |
| P3-07 | Chức năng ổn định | Kiểm thử luồng trọn vẹn; không còn lỗi chặn demo; log lỗi có thể truy vết. |
| T-01 | Kiểm tra đầu vào và thông báo rõ | Bộ ca test trường rỗng/sai định dạng/trùng mã/không tìm thấy; ảnh và kết quả thực tế. |
| T-02 | CSRF, middleware, auth, phân quyền | CSRF trên form; không dùng GET để ghi dữ liệu; policy kiểm tra phía server; test request trái phép. |
| T-03 | Minh chứng kiểm thử chức năng | Test case, kết quả tự động phù hợp, nhật ký test thủ công, danh sách lỗi và bằng chứng sửa. |
| D-01 | Chạy trên môi trường Laravel chuẩn | Hướng dẫn cài trên máy sạch; web server trỏ public; chạy web/worker/scheduler khi dùng. |
| D-02 | Database/migration/seed mẫu | Bộ seed có thể tạo lại; demo workspace A/B, role khác nhau, task/lịch/plan; không nhúng bí mật thật. |
| D-03 | Hướng dẫn cài và chạy | README; .env.example; phiên bản/phụ thuộc; lệnh tạo DB, build, chạy worker và cách xử lý lỗi thường gặp. |
| D-04 | Mã nguồn rõ và đủ file | Source, lockfiles, migrations, seeders, views, docs; rà gói nộp trước hạn. |
| G-01 | Phân công rõ | Bảng TV1–TV5, đầu việc, người chịu trách nhiệm, người review và đầu ra. |
| G-02 | Mỗi người có đóng góp phù hợp | Mỗi người sở hữu module đủ route/controller/model/view, test và tài liệu; có demo phần việc. |
| G-03 | Nhật ký, commit Git hoặc minh chứng tiến độ | Issue/PR/commit theo đầu việc; worklog hàng tuần; link bằng chứng, không đánh giá chỉ theo số commit. |
| G-04 | Phối hợp và đúng hạn | Họp tuần, quyết định ngắn có người phụ trách; cập nhật blocker; tích hợp từng tuần. |
| B-01 | Demo, trả lời phân tích/thiết kế/code | Kịch bản demo 12–15 phút; câu hỏi bảo vệ; từng người giải thích module mình làm và luồng MVC chung. |

Các ảnh không có trọng số điểm cụ thể nên kế hoạch không tự đặt trọng số.

## 5. Quy tắc nghiệp vụ cần ghi thành đặc tả

### 5.1. Tài khoản, workspace và quyền

- Người đăng ký có tài khoản hoạt động và workspace cá nhân theo luồng được duyệt. Mật khẩu băm; email duy nhất. Khi tạo workspace nhóm phải tạo role hệ thống và membership của owner trong một transaction.
- Workspace member ở trạng thái active mới được dùng quyền trong project. Người rời/bị đình chỉ không tiếp tục đọc tài nguyên chỉ vì đã biết URL.
- Role thuộc workspace; project_member và role của họ phải cùng workspace với project. Thành viên project con được khai báo riêng theo ERD V3.
- Owner có quyền quản trị workspace theo đặc tả; bảo vệ việc xóa owner cuối cùng/role hệ thống. Quyền xem workspace không tự đồng nghĩa có quyền đọc toàn bộ nội dung project nếu đặc tả không cấp.
- Lời mời Redis chứa email, workspace, role, người mời và thời hạn; chỉ email đăng nhập phù hợp mới nhận. Khóa xử lý và unique membership chống chấp nhận đồng thời. Ghi SQL thành công rồi mới tiêu thụ lời mời; có khả năng retry khi Redis/SQL lỗi từng phần.
- Thay đổi role không được tự cấp vượt quyền người thao tác; backend chỉ nhận permission nằm trong danh mục hợp lệ.

### 5.2. Project, cây task và tiến độ

- Project cha/con phải cùng workspace và không tạo chu trình. Dùng trạng thái draft/active/on_hold/completed/cancelled theo danh mục được chủ dự án chốt.
- V3 dùng `task_type`: GROUP/WORK/MILESTONE. GROUP gom mục tiêu; WORK là việc thực hiện; MILESTONE biểu diễn mốc. `group_label` là nhãn hiển thị, không phải vai trò hay quyền.
- Task cha/con cùng project; task cá nhân cùng workspace cá nhân. MVP đề xuất tối đa 3 cấp hiển thị, dù cấu trúc DB hỗ trợ sâu hơn; đây là giới hạn cần chủ dự án duyệt.
- Tính estimate và tiến độ từ WORK lá để tránh cộng trùng cha/con. Công thức đề xuất: tổng estimate WORK lá DONE / tổng estimate WORK lá; nếu chưa có estimate thì dùng tỷ lệ số WORK lá DONE và ghi rõ phương pháp. Không cộng lẫn hai phương pháp trong cùng chỉ số.
- MILESTONE không tự mang estimate lao động. Quy tắc milestone hoàn thành/nhóm tự hoàn thành cần ghi cụ thể; đề xuất group hiển thị tiến độ tổng hợp, còn hành động đóng project cần người có quyền xác nhận.
- Trạng thái WORK: TODO → IN_PROGRESS → REVIEW → DONE; có CANCELLED, và luồng yêu cầu sửa từ REVIEW về IN_PROGRESS. Người có quyền review xác nhận DONE theo phạm vi đã chọn.
- Dependency MVP dùng FS, độ trễ không âm; không self-reference hoặc chu trình; không nối task qua project khác. Chưa xong predecessor thì chặn bắt đầu successor theo phương án MVP. Task đang có blocker không được đánh DONE trước khi xử lý hoặc người có quyền cho phép và ghi lý do.
- Mã task unique trong workspace; tạo mã an toàn khi có nhiều người tạo đồng thời. Form validation có kiểm tra trùng, DB unique vẫn là lớp bảo đảm cuối.
- Tạo GROUP dưới WORK đã có lịch/estimate/time entry phải bị từ chối hoặc có luồng chuyển đổi rõ. Không âm thầm đổi WORK lá thành nhóm làm mất cách tính số liệu.
- Xóa task chỉ cho bản nháp chưa có lịch sử quan trọng; với task đã hoạt động ưu tiên lưu trữ/hủy. Dependency và task con phải được xử lý rõ trước khi xóa.

### 5.3. Lịch và thời gian

- Dùng `calendar_events` V3 cho NOTE/MEETING/BUSY/EXTRA_AVAILABLE/TASK_BLOCK. TASK_BLOCK cần task_id; các FK workspace/project/plan nếu có phải nhất quán.
- NOTE không bận có thể chồng lịch. TASK_BLOCK/MEETING/BUSY kiểm tra xung đột theo quy tắc đã duyệt; EXTRA_AVAILABLE thêm capacity ngoài lịch mặc định nhưng không tự xóa sự kiện bận đã có.
- Giờ làm mặc định nằm trong config BE; FE đọc cùng cấu hình. Lưu cấu hình đã dùng vào snapshot AI. Không thêm giờ mặc định vào users hay user_settings.
- Lưu thời điểm theo UTC; chuyển đổi theo timezone để nhập/hiển thị. Sự kiện all-day dùng khoảng ngày kết thúc loại trừ; thử qua nửa đêm và khi đổi timezone.
- `participants` là JSON nhỏ theo V3: backend chỉ cho chọn người trong phạm vi được phép, giới hạn số phần tử. MVP một sự kiện có một chủ sở hữu; trước khi hỗ trợ lịch chung đầy đủ cần chốt quyền người tham gia và cách kiểm tra trùng lịch của họ.
- Lịch riêng chỉ chia sẻ thông tin bận/rảnh cần thiết; không hiển thị tiêu đề/nội dung riêng cho người không có quyền.
- Time entry là mở rộng: một timer đang chạy/người; thời gian kết thúc phải sau bắt đầu; actual được ghi thực tế, không suy ra từ status task.

### 5.4. AI và plan

- AI output phải qua schema validation: loại task, task tạm/ID, dependency, người thực hiện, estimate, deadline/lịch và workspace. Không nhận SQL hay câu lệnh thực thi từ nội dung sinh ra.
- `plans.data` có `schema_version` và mã task tạm; khi áp dụng phải ánh xạ mã tạm sang task ID thật để nối dependency/lịch. Giữ mapping trong dữ liệu áp dụng phù hợp, không phụ thuộc tên task.
- Plan nháp có thể sửa; plan đã áp dụng giữ nguyên snapshot. Chỉnh tiếp tạo phiên bản mới trong cùng `plan_group_id`, liên kết `parent_plan_id`.
- Chỉ một bản active/group; đề xuất tạo nhánh mới phải kiểm tra base version. Duyệt cùng lúc phải có lock và ràng buộc DB phù hợp engine; không dùng unique(group_id, is_active) vì sẽ chặn nhiều bản inactive.
- Xác nhận và áp dụng trong một transaction cho task/dependency/event/activity/plan. Trước khi áp dụng kiểm tra lại task, membership và lịch thay đổi từ snapshot. Khi lỗi thời phải hiển thị xung đột và yêu cầu lập lại/xem lại; không âm thầm ghi đè thay đổi của người dùng.
- AI job chạy nền: form POST tạo ai_run → redirect sang trang Blade trạng thái → job gọi nhà cung cấp → ai_calls lưu từng bước/thử → tạo plan proposed hoặc lỗi → người dùng tải lại trang để xem. MVP có nút làm mới, polling cùng domain chỉ thêm khi cần.
- Gọi AI ở ngoài transaction dài. Chỉ mở transaction ngắn lúc ghi kết quả/áp dụng. Retry có idempotency key; giới hạn timeout, số thử, số token và chi phí do chủ dự án duyệt.
- Không gửi token tích hợp, mật khẩu hay dữ liệu lịch riêng không cần thiết. Nhà cung cấp/model và ngân sách chưa chọn là phụ thuộc cần chốt trước tuần AI.

### 5.5. Activity, thông báo và báo cáo

- `task_activities` lưu mỗi COMMENT/HISTORY/ATTACHMENT một dòng. COMMENT được sửa/xóa theo quyền; HISTORY bất biến; attachment chỉ lưu metadata. Ghi history từ thao tác server thực tế.
- `notifications` có một dòng/người nhận; `deliveries` JSON chứa trạng thái từng kênh. `read_at` không thay thế trạng thái gửi. Khóa khi nhiều job cập nhật JSON để tránh mất kết quả của kênh khác; dedupe_key gồm sự kiện/người nhận.
- Cấu hình thông báo nằm trong `user_settings.settings`; JSON có schema và default. Form settings không được nhận thêm các trường quyền/token.
- Snapshot báo cáo lưu metrics do server tính tại thời điểm tạo, kỳ [start,end), phạm vi và timezone. Nếu chưa có time entry thì báo cáo chỉ hiển thị planned/estimate, không gọi đó là giờ actual.
- GitHub log chỉ gắn một task theo V3. Sự kiện nhiều mã task giữ chưa gắn và thông báo để xử lý; không tự chọn mã đầu tiên. Số commit là tín hiệu hoạt động, không phải điểm hiệu suất thành viên.

## 6. Database, migrations và model

### 6.1. Danh mục 20 bảng theo V3

| Nhóm | Bảng | Lưu ý |
|---|---|---|
| Tài khoản và phạm vi | users, user_settings, workspaces, roles, workspace_members | UUID và unique email/membership/role name; owner nhất quán với membership. |
| Dự án và công việc | projects, project_members, tasks, task_dependencies, task_blockers, task_activities | Tự tham chiếu cha/con, kiểm tra workspace; activity có FK plan. |
| Lịch | calendar_events, time_entries [EXT] | Task blocks và ngoại lệ nằm trong event_type; time entry không bắt buộc cho MVP. |
| AI và kế hoạch | ai_runs, ai_calls, plans | Versioning, schema JSON, lock và retry. |
| Tích hợp | integration_connections [EXT], github_logs [EXT] | Credential mã hóa; project trỏ tới connection; xác minh webhook. |
| Thông báo/báo cáo | notifications, report_snapshots | Preference trong user_settings; deliveries trong notifications JSON. |

Các bảng hạ tầng Laravel `migrations`, `sessions`, `jobs`, `failed_jobs`, `cache`, password reset… không tính vào 20 bảng nghiệp vụ. Chỉ giữ/tạo bảng hạ tầng tương ứng driver và chức năng sử dụng.

### 6.2. Thứ tự tạo bảng dự kiến

1. users → user_settings → workspaces → roles → workspace_members.
2. integration_connections → projects → project_members.
3. tasks → task_dependencies → task_blockers.
4. ai_runs → ai_calls → plans.
5. calendar_events → time_entries → task_activities.
6. notifications → report_snapshots → github_logs.

Self-FK được tạo sau khi có cột/bảng tương ứng. Nếu chưa tạo bảng EXT trong đợt đầu thì thêm FK phụ thuộc trong migration sau, không để migration gọi tới bảng chưa tồn tại. Down/rollback theo thứ tự ngược.

### 6.3. Việc phải hoàn thành ở mỗi bảng

- Lập từ điển cột: nullable/default/type/constraint/index, enum hợp lệ và chính sách xóa FK.
- Đổi user ID và FK/session tương ứng sang UUID theo V3 trước khi có dữ liệu nghiệp vụ. Không dùng `migrate:fresh` trên DB có dữ liệu cần giữ.
- Model có đúng belongsTo/hasMany/self relationship; JSON/boolean/datetime casts; fillable/guarded; ẩn credentials khi serialization.
- Một số bảng V3 chỉ có created_at hoặc không có updated_at: cấu hình timestamps trong model cho đúng, không để Eloquent tự ghi cột không tồn tại.
- Unique: (workspace_id,user_id), (workspace_id,name) của role, (project_id,workspace_member_id), (workspace_id,code), cặp dependency, (ai_run_id,step_key,attempt_no), (plan_group_id,version_no), user_settings.user_id và khóa chống trùng tích hợp/thông báo.
- Index theo truy vấn thực tế: task(workspace_id,project_id,status), task(assignee_id,deadline_at), activity(task_id,type,created_at,id), events(user_id,starts_at), plan(plan_group_id,version_no), notifications(recipient_id,read_at,created_at). Rà bằng query thực tế trước khi thêm index rộng hơn.
- Workspace/project scope phải được kiểm tra khi tạo FK; có thể dùng composite constraints ở chỗ phù hợp, còn quy tắc nghiệp vụ kiểm tra trong Request/Service.
- Seeder dùng dữ liệu giả có cấu trúc: 2 workspace nhóm để thử ranh giới, workspace cá nhân, 5 user, owner/manager/member/viewer, 3 project có project con, khoảng 40 task đủ trạng thái, dependency/blocker, 20 event, activity và vài plan/snapshot mẫu.
- Seed plan AI mô phỏng phải ghi rõ là dữ liệu mẫu. Demo tích hợp thật dùng kết quả thật và phân biệt với fixture khi provider tạm lỗi.

## 7. Danh mục màn hình, controller và web route

Mọi tên dưới đây là danh mục dự kiến để chia việc, không phải code đã được tạo.

| Module | Blade/màn hình | Controller | Route/hành vi chính |
|---|---|---|---|
| Auth | đăng ký, đăng nhập | RegisteredUserController, SessionController | GET/POST register, GET/POST login, POST logout. |
| Hồ sơ | thông tin cá nhân, đổi mật khẩu, settings | ProfileController, UserSettingController | GET edit; PATCH profile/password/settings. |
| Workspace | danh sách, tạo/sửa, trang tổng quan | WorkspaceController | Resource routes; đổi workspace bằng thao tác được kiểm soát. |
| Role/member | bảng vai trò, form permission, thành viên, lời mời | RoleController, WorkspaceMemberController, InvitationController | Resource roles; gửi/thu hồi/chấp nhận lời mời; đổi role/status member. |
| Project | danh sách, chi tiết, cây project, form | ProjectController, ProjectMemberController | Resource trong workspace; thêm/gỡ member, lưu trữ/khôi phục. |
| Task | danh sách, cây, chi tiết, form, phần activity | TaskController, TaskStatusController, TaskActivityController | Resource trong workspace/project; PATCH status; POST comment/upload; GET download có quyền. |
| Dependency/blocker | các khối trong chi tiết task | TaskDependencyController, TaskBlockerController | Thêm/xóa dependency; báo/giải quyết blocker. |
| Calendar/Timeline | tháng/tuần/danh sách, form sự kiện, timeline project | CalendarEventController, TimelineController | GET calendar/timeline; CRUD event theo loại. |
| AI | form yêu cầu, trang trạng thái, log tóm tắt có quyền | AiRunController | POST yêu cầu planning/replanning/assistance; GET show; retry khi hợp lệ. |
| Plan | danh sách phiên bản, đề xuất cây task/lịch, so sánh và xác nhận | PlanController, PlanReviewController | GET index/show/edit; PATCH draft; POST propose/approve/reject/apply theo state machine. |
| Dashboard/report | tổng quan, lọc kỳ/phạm vi, snapshot | DashboardController, ReportController | GET dashboard/report; POST tạo snapshot; GET snapshot/export được phép. |
| Notification | inbox, badge chưa đọc, cài đặt | NotificationController | GET inbox; PATCH đọc/đọc tất cả; settings theo user. |
| Tích hợp [EXT] | trạng thái kết nối, GitHub logs | IntegrationController, GitHubLogController | Kết nối/ngắt; log chỉ đọc; webhook riêng khi tích hợp. |

Quy ước resource: GET index/create/show/edit; POST store; PATCH/PUT update; DELETE destroy. Form Blade dùng @csrf và @method cho phương thức tương ứng. Nested route binding vẫn phải kiểm tra membership và parent scope ở server.

### 7.1. Trải nghiệm sử dụng chung

- Layout chính có chọn workspace, sidebar, breadcrumb, thông báo và hồ sơ. Mỗi màn hình có tiêu đề và hành động chính rõ ràng.
- Form có label, đánh dấu bắt buộc, lỗi ngay dưới trường, giữ old input; select người nhận chỉ chứa thành viên phù hợp.
- Lists có keyword, bộ lọc status/assignee/project/priority, cột sort cho phép, phân trang; nút reset filter và trạng thái không có kết quả.
- Không có dữ liệu: gợi ý tạo đầu tiên theo quyền; không có quyền: trang 403 dễ hiểu; không tìm thấy: 404; lỗi server: thông báo chung và mã tra cứu log khi cần.
- Xác nhận trước xóa/lưu trữ/áp dụng plan; chống double submit; trang AI đang chờ có nút làm mới, thông báo thất bại và retry hợp lệ.
- Kiểm tra bằng bàn phím, focus label, tương phản màu; màu status luôn kèm chữ. Giao diện tiếng Việt thống nhất.

## 8. Phân công nhóm 5 người

| Người | Module chính theo MVC | Công việc liên quan | Review chéo |
|---|---|---|---|
| TV1 — điều phối/nền tảng | Auth, User/Profile, Workspace | Migrations chung, role catalog, tích hợp nhánh, hướng dẫn cài | Review TV2; TV2 review phần của TV1. |
| TV2 — project/phân quyền | Role, Member, Invitation, Project | Policy/middleware, ma trận quyền, use case quản trị | Review TV1; phối hợp TV3 về task scopes. |
| TV3 — task | Task/Sub-task, Dependency, Blocker, Activity/Attachment | Cây task, trạng thái, số liệu tiến độ; test chức năng task | Review TV4; TV4 review phần của TV3. |
| TV4 — lịch/giao diện chung | Calendar/Timeline, Settings, Notification | Blade layout/components, trải nghiệm lỗi, time entry nếu bật | Review TV3; hỗ trợ layout cho mọi thành viên. |
| TV5 — AI/báo cáo | AiRun/AiCall/Plan, Dashboard/Report | Adapter AI, job, plan approval; GitHub mở rộng nếu đủ thời gian | TV1/TV3 review plan transaction; TV5 review báo cáo/số liệu của nhóm. |

Mỗi người viết route, controller, request, policy/model liên quan, Blade và test/documentation cho module mình; không giao một người chỉ làm báo cáo hoặc CSS. TV4 tạo layout chung nhưng các thành viên vẫn chịu trách nhiệm màn hình module của mình.

Không cho nhiều người sửa cùng migration đã chia; TV1 quản lý thứ tự và việc gộp schema. API/AI provider và UX cần chủ dự án quyết định được đưa vào danh sách decision log, không tự thay đổi ERD.

### 8.1. Cách phối hợp và minh chứng

- Mỗi đầu việc có mã (ví dụ TASK-03), owner, điều kiện hoàn thành, ảnh/video và PR tương ứng.
- Tạo nhánh `feature/<module>-<task>` từ main đã đồng bộ; PR phải có mục đích, ảnh màn hình, cách kiểm tra, migration/config mới và điểm cần người review chú ý.
- Commit theo một thay đổi có ý nghĩa; ghi thông tin người đóng góp đúng thực tế. Mọi thành viên có worklog tuần; không tạo commit giả để đủ số lượng.
- Mỗi tuần tích hợp ít nhất một luồng chạy được; họp ngắn ghi đầu ra, blocker, người xử lý và hạn giải quyết. Các thay đổi ERD có decision log và cập nhật tài liệu.
- Chỉ gộp khi người khác đã đọc và chức năng đạt tiêu chí. Khi có conflict, người phụ trách module phối hợp giải quyết và kiểm tra lại luồng bị ảnh hưởng.

## 9. WBS và lịch đề xuất 10 tuần

### Tuần 1 — Đặc tả, môi trường và thiết kế khóa đầu tiên

- TV1: đồng bộ remote, kiểm tra runtime/Composer/pnpm, chuẩn hóa script chạy Windows; không dùng Pail khi máy thiếu pcntl. Lập .env.example và database/Redis dùng chung theo cấu hình đã chọn.
- TV2: actor/use case và ma trận quyền workspace/project/plan; mô tả membership active và owner.
- TV3: đặc tả cây GROUP/WORK/MILESTONE, transitions, dependency và tiến độ.
- TV4: wireframe Blade cho login/dashboard/project/task/calendar; quy ước layout/form/error.
- TV5: đặc tả luồng AI/plan, output schema v1 và báo cáo; xác định điểm cần chủ dự án chọn provider.
- Cả nhóm: rà ERD V3 với các quy tắc ở mục 5, tạo data dictionary, đổi user UUID, dựng migrations theo dependency.
- **Mốc G1:** bộ đặc tả/ERD/quyền được chủ dự án chốt; môi trường trống tạo được database mẫu; có layout và trang đăng nhập khung.

### Tuần 2 — Auth, workspace, role và membership

- Hoàn thành register/login/logout, regenerate session, profile và giới hạn login thất bại.
- Workspace CRUD; tạo owner/role/membership atomic; workspace cá nhân và nhóm.
- Role CRUD với danh mục permission; member list/đổi role/status; lời mời Redis có TTL, email preview/local mail và luồng chấp nhận.
- Chạy kiểm tra đăng nhập sai, thiếu dữ liệu, email trùng, role trái phép, URL workspace khác và nhận invite lặp.
- **Mốc G2:** hai user dùng hai workspace riêng, không truy cập chéo; owner và member có quyền khác nhau; có minh chứng P3 auth/CRUD/validation/CSRF.

### Tuần 3 — Project và CRUD task xuyên suốt

- Project CRUD, project cha/con, project membership; không tự thừa kế quyền project con.
- Task CRUD có form Blade; danh sách, bộ lọc, sort, pagination; mã task unique; detail và phân công.
- Hoàn thiện component form/table/flash/confirm và trang 403/404.
- **Mốc G3:** demo được login → workspace → project → task → sửa/lọc/xóa hoặc lưu trữ theo quyền. Đây là bản nền bắt buộc trước AI.

### Tuần 4 — Cây task, trạng thái, dependency, blocker và activity

- GROUP/WORK/MILESTONE, thứ tự hiển thị, chống chu trình và scope; tiến độ từ WORK lá.
- Transition trạng thái và reviewer; dependency FS, blocker; activity COMMENT/HISTORY/ATTACHMENT.
- Upload/download có quyền, validation loại/dung lượng và tên file an toàn; history ghi đúng thay đổi, không cho người dùng sửa.
- **Mốc G4:** tạo/sửa cây task không sai scope; predecessor và blocker ảnh hưởng thao tác theo quy tắc; activity phân trang; hoàn thành CRUD chính và minh chứng P3.

### Tuần 5 — Calendar/Timeline và thông báo trong ứng dụng

- CRUD calendar theo event_type; kiểm tra timezone, xung đột lịch, all-day, TASK_BLOCK và sự kiện riêng.
- Timeline project dựa trên calendar_events; tải theo phạm vi ngày, không lấy toàn bộ dữ liệu hệ thống.
- Inbox, unread badge, mark read, user_settings; thông báo giao task và plan chờ duyệt ở mức dữ liệu chuẩn bị.
- **Mốc G5:** người dùng xem task và lịch tương ứng, form không tạo khoảng thời gian sai hoặc lộ lịch riêng; settings không nhận trường lạ.

### Tuần 6 — Dashboard/báo cáo và nền tảng AI

- Dashboard theo quyền: số task theo trạng thái, quá hạn, blocker, tải kế hoạch theo người; không bịa giờ actual nếu chưa có time entry.
- Tạo report_snapshots theo kỳ, lọc member/project, bản in Blade và export được chọn; số liệu truy được về nguồn.
- Queue worker; ai_runs/ai_calls; adapter provider; form gửi yêu cầu và trang trạng thái/lỗi.
- **Mốc G6:** báo cáo snapshot giữ nguyên khi dữ liệu sau đó thay đổi; job AI có timeout/retry và không treo web request.

### Tuần 7 — AI planning và duyệt plan

- AI phân rã cây task, estimate, phân công và lịch từ bối cảnh đúng quyền; validation output và schema.
- Trang Blade xem/chỉnh nháp và so sánh phiên bản; thao tác đề xuất/từ chối/duyệt.
- Áp dụng plan transaction; mapping task tạm; chống double apply; chỉ một active version/group; kiểm tra base và dữ liệu lỗi thời.
- **Mốc G7:** demo một yêu cầu AI thật → người dùng sửa → duyệt → task và calendar cập nhật đúng; đề xuất chưa duyệt không đổi dữ liệu thật; lỗi giữa chừng không để dữ liệu nửa vời.

### Tuần 8 — AI replanning, hỗ trợ blocker và mở rộng đã chọn

- Task trễ/bị chặn: phân tích ảnh hưởng dependency → đề xuất plan mới → người dùng xem thay đổi → duyệt; không tự áp dụng.
- Hỏi trợ lý về blocker; giới hạn bối cảnh và quyền log; giữ dữ liệu cũ để giải thích quyết định.
- Nếu G1–G7 ổn: chọn tối đa một tích hợp ưu tiên (GitHub logs hoặc email tổng hợp) và time entry nếu chủ dự án cần.
- **Mốc G8:** chuỗi planning/replanning chạy được; thống kê báo cáo chính xác; phạm vi mở rộng cuối cùng được khóa.

### Tuần 9 — Kiểm thử tổng thể và cài trên máy khác

- TV1–TV5 kiểm thử chéo, đóng lỗi theo mức nghiêm trọng; ưu tiên lỗi lộ dữ liệu, mất dữ liệu, sai quyền, sai số liệu, sai áp dụng plan và các lỗi chặn demo.
- Test setup trên môi trường mới theo README: migration, seed, build, Redis, worker, scheduler nếu có.
- Chốt bộ seed demo; chụp bằng chứng theo ma trận tiêu chí; cập nhật report và sơ đồ đúng code.
- **Mốc G9:** không còn lỗi chặn luồng chính; có bảng kết quả test và tài khoản demo; cài được từ hướng dẫn mà không cần hỏi người viết code.

### Tuần 10 — Hoàn thiện hồ sơ, rehearsal và gói nộp

- Đóng băng tính năng; chỉ sửa lỗi ảnh hưởng tiêu chí/demo. Kiểm tra lại phần bị tác động sau mỗi sửa.
- Dọn README, sơ đồ, worklog, PR/commit links, báo cáo kiểm thử, hướng dẫn vận hành; đối chiếu đủ các dòng tiêu chí.
- Diễn tập bảo vệ: mỗi người demo và giải thích code; chạy bài demo từ dữ liệu sạch và tình huống lỗi.
- Tạo bản phát hành/gói nộp có source/lockfiles/migrations/seeders/docs và mẫu cấu hình; rà bí mật và khả năng giải nén/chạy.
- **Mốc G10:** bản nộp cuối đã kiểm tra; video dự phòng và fixture AI ghi nhãn rõ; từng thành viên nắm luồng MVC và phần việc của mình.

### 9.1. Phụ thuộc và cách rút ngắn lịch

Đường triển khai chính: G1 → G2 → G3 → G4 → G5 → G6 → G7 → G8 → G9 → G10. Sau G1, TV4 có thể dựng Blade layout và calendar độc lập bằng seed; TV5 có thể làm adapter/schema AI với fixture, nhưng áp dụng plan chỉ tích hợp sau khi task/calendar ổn.

Nếu chỉ còn 6–8 tuần, chủ dự án cần chọn giảm tính năng: giữ CRUD/permission/Blade/validation/test/deploy/report + một luồng AI planning có duyệt; rút Google Calendar, Telegram, time entry, GitHub và replanning nâng cao. Không rút phần minh chứng kiểm thử, seed và hướng dẫn cài vì các tiêu chí này được nêu trực tiếp trong ảnh.

## 10. Kế hoạch kiểm thử và tiêu chí nghiệm thu

Mỗi module có kiểm thử tự động cho logic/quyền quan trọng và kiểm thử thủ công trên Blade. Không chỉ kiểm tra status 200: đối chiếu thay đổi trong DB, phạm vi quyền và việc hiển thị lỗi/dữ liệu giữ lại.

| ID | Tình huống | Kết quả mong đợi |
|---|---|---|
| AUTH-01 | Login đúng/sai; user bị khóa; logout rồi quay lại URL riêng | Session đúng; lỗi rõ; user không hợp lệ bị chặn; logout mất quyền. |
| AUTH-02 | Đăng ký email trùng, mật khẩu thiếu, submit lặp | Lỗi tại trường; không tạo user hoặc workspace trùng. |
| SEC-01 | Submit form ghi dữ liệu thiếu/sai CSRF | Request bị từ chối, DB không thay đổi. Lưu ý test HTTP Laravel có thể không bật CSRF như chạy thật; cần ca browser/request thực tế. |
| SEC-02 | Đổi UUID trong URL để xem/sửa/xóa tài nguyên workspace khác | 403/404 theo quy ước, không lộ nội dung hoặc thay đổi dữ liệu. |
| SEC-03 | Member/viewer gửi trực tiếp request mà nút đã bị ẩn | Policy phía server chặn; không dựa vào việc ẩn nút Blade. |
| ROLE-01 | Cấp permission không có trong catalog hoặc vượt quyền | Validation/authorization từ chối. |
| INV-01 | Invite hết hạn/sai email/chấp nhận đồng thời hai lần | Lỗi hoặc thông báo đã tham gia; tối đa một membership; retry có thể phục hồi. |
| PRJ-01 | Project làm cha của chính nó hoặc hậu duệ; parent khác workspace | Từ chối, cây không đổi. |
| TASK-01 | Task thiếu title, mã trùng, assignee không thuộc project | Lỗi tiếng Việt; không ghi dữ liệu không hợp lệ. |
| TASK-02 | Search/filter/sort/pagination kết hợp | Đúng kết quả và quyền; giữ filters; input sort không thực thi cột tùy ý. |
| TREE-01 | Task self-parent/chu trình/đổi WORK có dữ liệu thành nhóm | Bị chặn theo quy tắc; không tính estimate hai lần. |
| DEP-01 | Dependency lặp/khác project/chu trình; predecessor chưa DONE | Từ chối hoặc chặn transition đúng đặc tả. |
| ACT-01 | Comment có script; cố sửa HISTORY; truy cập attachment của người khác | Nội dung escape; history bất biến; file bị chặn ngoài phạm vi. |
| FILE-01 | Sai MIME/quá dung lượng/tên có đường dẫn | Từ chối hoặc lưu tên nội bộ an toàn; không thực thi file. |
| CAL-01 | Start >= end; all-day; qua nửa đêm; event busy trùng lịch | Validation đúng; timezone nhất quán; trường hợp cho chồng được phân biệt theo event_type. |
| CAL-02 | Xem calendar người khác; participants ngoài workspace | Không lộ nội dung riêng; danh sách người tham gia được kiểm tra. |
| AI-01 | Provider timeout/lỗi; output JSON sai/sai member/task reference | Run failed hoặc đề xuất lỗi rõ; không tạo task thật; retry có giới hạn. |
| PLAN-01 | Plan mới proposed nhưng chưa duyệt | Không thay đổi task/calendar đang dùng. |
| PLAN-02 | Hai người duyệt cùng lúc/nhấn duyệt lặp | Một lần áp dụng; một bản active/group; không tạo task trùng. |
| PLAN-03 | Task/lịch/member thay đổi sau khi sinh proposal | Phát hiện stale/conflict và yêu cầu xem lại, không ghi đè âm thầm. |
| PLAN-04 | Lỗi khi đang cập nhật một phần task/calendar | Transaction rollback; plan không được đánh applied sai. |
| REPORT-01 | Đối chiếu kỳ báo cáo, timezone, WORK lá, dữ liệu cancelled | Metrics đúng công thức; snapshot không đổi về sau; số liệu bị giới hạn theo quyền. |
| NOTI-01 | Gửi trùng, nhiều kênh cập nhật deliveries, mark read | Một thông báo/sự kiện/người; không mất trạng thái JSON; đọc và gửi tách biệt. |
| GIT-01 [EXT] | Webhook chữ ký sai/trùng delivery/nhiều task code | Từ chối chữ ký sai; không xử lý lặp; không gắn nhầm task. |
| DEPLOY-01 | Clone/gói source trên máy mới, làm đúng README | Chạy được web, DB seed, Redis và worker; build không phụ thuộc file bị thiếu. |

### 10.1. Hồ sơ bằng chứng

- `docs/testing/test-cases.md`: tiền điều kiện, bước, dữ liệu, kết quả mong đợi/thực tế, pass/fail, người/ngày test và link ảnh/log.
- `docs/testing/bugs.md`: lỗi, mức ảnh hưởng, bước tái hiện, người sửa, commit sửa và kết quả kiểm tra lại.
- Kết quả test tự động liên quan, ảnh Blade, video luồng chính; không dùng ảnh test mặc định Laravel để chứng minh nghiệp vụ.
- Chỉ coi một module xong khi có route/controller/model/view, validation/quyền, các trạng thái lỗi và minh chứng tương ứng.

## 11. Triển khai, cấu hình và gói nộp

### 11.1. Chuẩn hóa môi trường

- Ghi phiên bản PHP/Composer/database/Redis/Node và package manager trong README; dùng lockfiles và tránh mỗi người cài một bộ dependency khác.
- Thống nhất pnpm hoặc npm trong scripts. Hiện có pnpm-lock nhưng Composer script mặc định gọi npm/npx; phải sửa khi triển khai để lệnh hướng dẫn khớp cách chạy thật.
- .env.example có đủ biến DB/Redis/queue/mail/AI và giá trị minh họa. .env thật, token, dữ liệu cá nhân và file private không vào Git/gói source công khai.
- Tách môi trường demo khỏi dữ liệu đang dùng. Khi cập nhật có backup và kế hoạch rollback migrations/data; không dùng migrate:fresh trên hệ thống có dữ liệu cần giữ.

### 11.2. Runbook cài đặt phải viết và kiểm tra

1. Clone hoặc giải nén source → cài Composer dependency theo lockfile.
2. Sao chép .env.example thành .env, cấu hình DB/Redis/mail/AI → tạo APP_KEY cho cài đặt mới. Giữ APP_KEY cũ khi nâng cấp dữ liệu đã mã hóa.
3. Tạo database rỗng → chạy migrate và seeder demo theo hướng dẫn riêng môi trường.
4. Cài frontend bằng package manager đã chốt → build Vite assets.
5. Cấp quyền ghi storage/bootstrap cache; tải tệp private qua controller. Chỉ tạo public storage link nếu thật sự có tài sản công khai.
6. Local: chạy web server phát triển và worker riêng; nếu có tác vụ định kỳ thì chạy scheduler trong lúc demo.
7. Môi trường triển khai: document root trỏ `public`, HTTPS khi public, `APP_DEBUG=false`, cache cấu hình/view/route phù hợp; worker/scheduler được giám sát và restart sau cập nhật.
8. Mở trang đăng nhập → đăng nhập seed → tạo task → gửi job AI thử → kiểm tra notification/log; đây là kiểm tra sau cài đặt.

### 11.3. Bộ tài liệu nộp

| Tệp/nhóm | Nội dung |
|---|---|
| README.md | Mục tiêu, tính năng, thành viên, phiên bản, cài/chạy, seed accounts, worker/Redis, lỗi thường gặp. |
| docs/requirements | Actor, scope, use case, quy trình, yêu cầu phi chức năng, acceptance criteria. |
| docs/design | ERD V3 đã cập nhật theo code; data dictionary; sơ đồ chức năng; luồng MVC; ma trận quyền; sequence AI/plan. |
| docs/testing | Test cases, kết quả, bugs, ảnh/video và minh chứng bảo mật. |
| docs/team | Phân công, worklog, decision log, bảng link Issue/PR/commit theo người. |
| docs/deployment | Cài trên môi trường mới, cấu hình demo, backup/rollback, gói phát hành. |
| Source + lockfiles | Toàn bộ Blade/model/controller/routes/request/policy/service/job; migration/factory/seeder; .env.example. |
| Hồ sơ bảo vệ | Slide/demo script; báo cáo; phân công trình bày; video dự phòng. |

Không mặc định phải có file SQL dump khi migration+seeder đã đủ. Nếu giảng viên yêu cầu SQL, xuất thêm bản demo đã loại bí mật; migration vẫn là nguồn quản lý schema.

## 12. Demo bảo vệ đề xuất 12–15 phút

1. **1 phút — TV1:** bài toán, phạm vi, actor, kiến trúc MVC/Blade và ERD V3.
2. **2 phút — TV1/TV2:** login, workspace/role/member; dùng tài khoản ít quyền chứng minh bị chặn thao tác không được phép.
3. **3 phút — TV3:** project → cây task → CRUD → tìm/lọc/sort/phân trang → dependency/blocker → comment/file/history; cho thấy form sai và lỗi rõ.
4. **2 phút — TV4:** Calendar/Timeline; khung task, sự kiện bận và thông báo; thể hiện một lỗi giờ sai/xung đột.
5. **3 phút — TV5:** gửi yêu cầu AI thật, xem/chỉnh proposal, duyệt và kiểm tra task/lịch; chỉ ra version và thay đổi activity. Dùng dữ liệu mẫu có nhãn nếu cần demo dự phòng khi provider lỗi.
6. **1–2 phút — TV5/TV1:** dashboard/snapshot, test evidence, hướng dẫn cài và minh chứng đóng góp nhóm.
7. **1–2 phút — cả nhóm:** một người giải thích luồng form Blade → route → Form Request/Policy → Controller/Model → redirect/view; người khác giải thích transaction/UUID/JSON theo câu hỏi.

Câu hỏi cần luyện: vì sao chọn roles JSON; phân biệt auth và authorization; chống đọc chéo workspace; chống chu trình; cách tính tiến độ cha/con; nguồn dữ liệu lịch; vai trò ai_runs/ai_calls; chống apply plan lặp/lỗi thời; vì sao MVC vẫn có job và gọi API AI bên ngoài; seed và migration khác nhau thế nào; CSRF khác kiểm tra quyền thế nào.

## 13. Rủi ro, điều kiện ra quyết định và bước khởi động

| Rủi ro | Biện pháp trong kế hoạch |
|---|---|
| Thiết kế tiếp tục thay đổi | Khóa V3 ở G1; thay đổi sau đó có decision log/migration/tài liệu cập nhật. |
| AI chiếm hết thời gian, CRUD chưa xong | G3/G4 là điều kiện tích hợp AI; TV5 dùng fixture cho adapter trước, không làm chậm luồng chính. |
| Quá nhiều mở rộng | G8 khóa phạm vi; ưu tiên GitHub hoặc email, Google/Telegram/time tracking có thể dời sau. |
| Sai quyền do workspace/project lồng nhau | Policy tập trung, query scope, test chéo tài khoản và UUID trực tiếp. |
| Mất dữ liệu khi đổi ID/migration | Làm UUID trước dữ liệu nghiệp vụ; backup và không reset dữ liệu thật bằng lệnh fresh. |
| Calendar gom nhiều loại gây khó kiểm tra | Định nghĩa rule theo từng event_type và scope rõ; test all-day/timezone/xung đột. |
| Provider/Redis/worker không chạy lúc demo | Có hướng dẫn vận hành, trang báo lỗi/trạng thái, video dự phòng, fixture ghi nhãn minh bạch. |
| Một người làm hết, thiếu minh chứng nhóm | Chia module dọc MVC, review chéo và worklog/PR theo đầu việc thực tế. |

### 13.1. Chủ dự án cần quyết định để bắt đầu

1. Duyệt V3 20 bảng làm cơ sở, với quy tắc cây task và calendar ở mục 5; các giới hạn đề xuất như số cấp/task transitions được chốt ở G1.
2. Xác nhận MySQL/MariaDB hay DB khác, cách chạy Redis, nơi demo/triển khai cuối và package manager thống nhất.
3. Xác nhận phạm vi bắt buộc: CRUD + phân quyền + lịch + báo cáo + AI planning có duyệt; chọn có làm replanning/hỗ trợ blocker ngay bản nộp không.
4. Cung cấp/điền tên 5 thành viên và quỹ giờ/hạn nộp để chuyển lịch tương đối 10 tuần thành lịch thật.
5. Chọn provider/model và mức ngân sách trước tuần 6; chọn tích hợp ưu tiên trước tuần 8.

### 13.2. Gói công việc khởi động sau khi giao triển khai

Mục tiêu 3–5 ngày làm việc đầu: đồng bộ repository; chốt yêu cầu và ma trận quyền; hoàn thiện ERD/data dictionary; chuẩn hóa cấu hình; UUID/migrations/seed cơ sở; layout Blade và khung trang login để chủ dự án xem sớm. Luồng workspace/project/task xuyên suốt được nghiệm thu ở tuần 3 theo G3. Chia nhánh module theo bảng phân công và các mốc G1–G10; điều chỉnh khối lượng ngày đầu theo quỹ giờ nhóm thực tế.

## 14. Tài liệu Laravel tham chiếu

Các lựa chọn kiến trúc và nghiệp vụ trong kế hoạch là đề xuất riêng cho dự án. Tài liệu chính thức dùng để đối chiếu cơ chế framework:

- [Controllers và resource routes](https://laravel.com/framework/docs/12.x/controllers)
- [Web route, session và cấu trúc thư mục](https://laravel.com/framework/docs/12.x/structure)
- [Validation và Form Request](https://laravel.com/framework/docs/12.x/validation)
- [Authorization và policies](https://laravel.com/framework/docs/12.x/authorization)
- [CSRF](https://laravel.com/framework/docs/12.x/csrf)
- [Queues](https://laravel.com/framework/docs/12.x/queues)
- [Deployment](https://laravel.com/framework/docs/12.x/deployment)

Đầu ra hiện tại: kế hoạch triển khai để chủ dự án duyệt; chưa tạo module, chạy migration nghiệp vụ hoặc push thay đổi mã nguồn.

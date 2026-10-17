# Phần 1 — Nền tảng database và luồng qua các hàm

## A. Flow cài đặt và seed

1. `php artisan migrate` chạy migration user UUID và các bảng hạ tầng trước, sau đó 19 migration nghiệp vụ còn lại theo thứ tự phụ thuộc.
2. Mỗi migration `up()` tạo bảng/cột/FK/unique/index. Tổng 20 bảng nghiệp vụ, 61 FK; session có thêm FK user thuộc hạ tầng.
3. `DatabaseSeeder::run()` gọi `DemoSeeder::run()`.
4. `DemoSeeder::run()` từ chối production; dùng `DB::transaction()` để hoặc ghi đủ fixture, hoặc rollback khi lỗi.
5. Tạo/lấy User và UserSetting bằng `firstOrCreate()`. `User.password` cast `hashed`; settings JSON cast `array`.
6. `DemoSeeder::workspace()` lấy/tạo 2 workspace nhóm và 1 cá nhân; `roles()` lấy/tạo Owner/Manager/Member/Viewer từ `config/permissions.php`.
7. Ghi workspace membership → project/project con → project membership → cây 40 task → dependency/blocker.
8. Tạo AiRun/AiCall/Plan mô phỏng, 20 calendar events, activity, notification và snapshot. Các bảng mở rộng vẫn sẵn schema nhưng không có credential hay tích hợp thật trong seed.
9. Chạy seed lần hai không thêm dữ liệu trùng và không reset những fixture đã bị người dùng chỉnh. Muốn reset chỉ làm trên DB demo riêng sau khi kiểm tra dữ liệu.

Tài khoản demo: demo1@example.test đến demo5@example.test; mật khẩu Demo@123456. Chưa có trang/route đăng nhập.

## B. Flow một lần ghi Eloquent

```text
Model::create(...) hoặc $model->update(...)
  → model casts / UUID
  → AppServiceProvider::boot() đã đăng ký IntegrityObserver
  → IntegrityObserver::saving($model)
  → ReferenceIntegrity::validate($model)
       → workspaceId(): xác định phạm vi trực tiếp hoặc từ project/task/ai_run
       → kiểm tra từng FK trong config/domain_schema.php
       → sameProject(): task/plan/event phải cùng phạm vi project
       → assertAcyclicParent(): phát hiện vòng cha/con
       → assertAcyclicDependency(): tìm đường quay lại trong đồ thị dependency
       → kiểm tra catalog quyền, range thời gian, số không âm,
         loại task/event, group/version plan và định danh scope
  → nếu sai: ValidationException::withMessages(), không ghi model
  → nếu đúng: SQL INSERT/UPDATE
  → DB kiểm tra FK / unique / generated-column unique
```

`IntegrityObserver::updating()` và `deleting()` từ chối sửa/xóa hoạt động HISTORY. COMMENT dùng SoftDeletes; không mất bản ghi vật lý khi xóa mềm.

Việc chống chu trình hiện áp dụng cho ghi Eloquent tuần tự. Những workflow nhiều bước/chạy đồng thời phải được bọc transaction/lock ở Service trong phần nghiệp vụ tương ứng; observer không thay thế cơ chế đó.

**Giới hạn cần hiểu:** Query Builder/raw SQL, `withoutEvents()` hoặc cập nhật hàng loạt có thể bỏ qua observer. DB vẫn giữ FK/unique nhưng không tự biết mọi quy tắc cùng workspace hay quyền người dùng. Các phần sau phải dùng Request/Policy/Service và không mở mass-write trực tiếp từ frontend.

## C. Flow đọc quan hệ

- `Task::with(['project', 'parent', 'assignee', 'activities'])->...`: Eloquent lấy task và quan hệ; Blade sau này nhận dữ liệu từ controller.
- `Task::forWorkspace($workspaceId)`: chỉ thêm filter workspace, không xác thực người gọi.
- `Task::workLeaves()`: lấy WORK không có con để làm đầu vào tính tiến độ; chưa có service dashboard/tính tiến độ ở phần 1.
- `Role::hasPermission($code)`: kiểm tra code có trong JSON role; chưa tự chọn role của người đăng nhập theo workspace/project.
- User có `notifications()` trỏ bảng notifications V3 riêng. Chưa dùng database notification channel mặc định Laravel vì schema V3 khác cấu trúc mặc định.

## D. Schema và ràng buộc kỹ thuật

- UUID cho primary/FK nghiệp vụ. User giữ email_verified_at/remember_token để tương thích auth Laravel; session.user_id cũng UUID.
- Nullable user FK dùng SET NULL khi phù hợp audit; phần lớn quan hệ còn lại RESTRICT để tránh xóa dây chuyền dữ liệu. Cây project/task/plan giữ self-FK RESTRICT.
- Khi rollback bảng self-reference, `down()` gỡ parent FK giữa các dòng trước khi DROP, để SQLite không bị RESTRICT chặn. Đây là thao tác hủy bảng của migration, chỉ dùng trong database thử nghiệm hoặc quy trình rollback đã xét dữ liệu.
- `plans.active_group_key` là generated UUID nullable: active thì bằng plan_group_id, inactive thì NULL. Unique bảo đảm nhiều phiên bản inactive nhưng chỉ một active/group.
- `time_entries.running_user_key` là generated UUID nullable: timer chưa kết thúc thì bằng user_id, đã kết thúc thì NULL. Unique bảo đảm một timer đang chạy/người.
- Hai cột kỹ thuật trên bổ sung ngoài ERD để thực thi constraint; hidden khỏi serialization, không có form input.
- Các bảng không có updated_at trong ERD đặt UPDATED_AT=null; ProjectMember không dùng timestamps vì ERD chỉ có joined_at. TaskActivity có SoftDeletes.
- IntegrationConnection.credentials_encrypted cast `encrypted:array`, hidden khỏi toArray. Chưa cần key nhà cung cấp; APP_KEY Laravel hiện hữu dùng mã hóa local và phải giữ khi có dữ liệu mã hóa.
- Các trạng thái là backed enum; schema cột string để dễ migration; enum validation ở Eloquent và Form Request của các phần sau.
- Định danh workspace/project của bản ghi đã có bị khóa ở observer; chuyển scope cần quy trình riêng để tránh làm lệch các quan hệ con. Plan group/version cũng không được sửa lại.

## E. Dữ liệu mẫu và tính xác thực

Dataset cố định phục vụ phát triển và minh họa, không đại diện tiến độ thực tế. Các bản ghi AI dùng provider fixture/model demo-only, có cờ fixture. Snapshot sample cũng được gắn fixture; chưa triển khai tính báo cáo động hoặc gửi notification thật.

Có 5 users; 3 workspaces; 12 roles; 7 workspace members; 3 projects; 8 project members; 40 tasks; 1 dependency; 1 blocker; 20 calendar events; 2 activity; 1 fixture AI run/call/plan; 1 notification; 1 snapshot. Các bảng time_entries/integration_connections/github_logs chưa có record fixture mặc định.

## F. Xác minh trực tiếp phần 1

Theo chỉ đạo mới của chủ dự án, hai lớp test vừa thêm đã được gỡ. Kiểm tra trực tiếp bằng chính Artisan/Eloquent/SQL trên SQLite có dữ liệu đã commit, đọc lại trong tiến trình khác và lưu exception thực tế. Chi tiết, lệnh tái hiện và log nằm ở `docs/verification/README.md`.

Đã chạy: migrate/seed hai lần; CRUD dữ liệu model; SQL JOIN đọc lại; thử unique mã task, scope role, chu trình task/dependency, số âm, giờ sai, history bất biến, comment soft delete; unique active plan/timer; mã hóa và ẩn credential; raw FK lỗi; migrate → seed → rollback → migrate → seed trên database lifecycle riêng. FK check không phát hiện vi phạm.

Dữ liệu workspace `Review Flow - Stage 01` và các record FLOW-* được giữ lại để chủ dự án xem ngoài dataset seed. Các plan của kiểm tra storage không phải kết quả workflow duyệt AI. Chưa có HTTP/controller nghiệp vụ nên không công bố đã kiểm tra đăng nhập, CSRF hay CRUD qua giao diện. MySQL/MariaDB chưa được chạy trên server thật.

## G. Điểm dừng để chủ dự án duyệt

Phần 1 hoàn tất nền DB/model. Phần tiếp theo sau khi duyệt: Auth/Workspace/Role/Member/Invitation, gồm Form Requests, Policies, Services và web controllers. Frontend/Blade được bàn giao qua `FRONTEND_HANDOFF.md`; không tạo giao diện nghiệp vụ ở lần này.

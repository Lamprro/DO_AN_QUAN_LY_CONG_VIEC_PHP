# Kịch bản demo và đối chiếu tiêu chí

Chỉ tiêu | Chỗ triển khai / minh chứng
---|---
PLO1: đối tượng, phạm vi, quy trình | docs/planning/KE_HOACH_TRIEN_KHAI_LARAVEL_MVC_V3.md; workflow phần1–8
PLO2: ERD, CSDL, migration/relations | docs/design/AI_Work_Management_ERD_V3.drawio, schema-v3.json; app/Models, database/migrations
PLO2: MVC, phân quyền | routes/web.php + các route module, app/Http/Controllers, Policies/Support, resources/views
PLO3: CRUD, search/filter/sort/page | project/task/members/calendar/report; verification/stage02..06
PLO3: form validation, auth/CSRF/error | Requests, services validation, middleware/policies; bằng chứng403/404/419 + lỗi validation
PLO3: hoạt động ổn định và dữ liệu mẫu | DemoSeeder có nhãn giả, flow dữ liệu thật final, fresh install riêng
PLO3: triển khai/hướng dẫn/file đầy đủ | DEPLOYMENT.md, README, dist source/manifest; migrations/seeds/lock
PLO6: phân công/đóng góp/nhật ký | docs/TEAM_WORKLOG.md là khung cho thành viên điền việc đã làm thật; không tạo commit/đóng góp giả
Bảo vệ/demo | Các bước bên dưới; video và trình bày thật cần nhóm thực hiện

Demo local đề xuất:

1. Đăng nhập demo1; mở workspace/project review hoặc tự tạo project. Cho thấy workspace role và project membership độc lập.
2. Tạo GROUP → WORK A/B; ước lượng, phân công, reviewer; lọc/sort/page và cây/tiến độ.
3. Thêm FS A→B, blocker, comment/private file. Thử bắt đầu B khi A chưa xong: có lỗi, trạng thái không đổi.
4. A IN_PROGRESS → bắt đầu timer → thử REVIEW bị chặn → dừng timer → REVIEW → reviewer DONE. FS có lag còn chờ được giải thích đúng.
5. Đặt TASK_BLOCK của chính mình, thử trùng busy bị chặn; xem Calendar/Timeline và privacy. Đổi settings, xem thông báo in-app theo quyền.
6. Xem dashboard, lưu report, đổi estimate rồi đọc report cũ để thấy snapshot cố định; CSV và bản in.
7. Tạo plan nháp từ JSON hợp đồng; sửa bằng version mới; đề xuất/duyệt/apply. Đọc lại task/dependency/lịch/history. Apply lần nữa không trùng; cập nhật nguồn rồi thử apply bản cũ bị chặn; tạo slot trùng thấy rollback.
8. Local schedule tạo version đề xuất theo giờ làm/FS, chưa tự apply. Impact nêu blocker/overdue/FS. AI không có key: request queued → worker → failed rõ lý do; không nói đó là kết quả AI thành công.
9. GitHub logs chỉ demo public khi đã có secret/domain thật; hiện có minh chứng chữ ký local và chống trùng, không nhận là đã gọi GitHub network.

Để bảo vệ code: giải thích Controller lấy input/binding → Policy/scope → Service transaction → Eloquent/observer → redirect Blade/old input/errors; mô tả vì sao không có REST API, vì sao không lấy estimate làm actual, vì sao plan phải có snapshot và phê duyệt.

Việc nhóm tự hoàn tất: điền tên/phân công thật, worklog/commit thực tế từng người; cài kiểm chứng trên máy thứ hai; quay demo; rà soát giao diện, báo cáo và yêu cầu riêng của giảng viên. Không có video/cuộc họp/đóng góp giả trong bộ bàn giao này.

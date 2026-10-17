# Xác minh phần 3 — HTTP, browser và database thật

17/10/2026. Laravel tại 127.0.0.1:8123, SQLite work_management.sqlite, session database. Không TestCase/mocks/PHPUnit. Không reset/rollback database, không xóa dữ liệu cũ để tạo minh chứng.

## Tệp bằng chứng

- http-walkthrough.py / http-flow.json: script tuần tự dùng urllib + CookieJar và form CSRF thật; SQL SQLite read-only sau các thay đổi quan trọng. Tái sử dụng helper từ stage02/http-walkthrough.py, không chạy lại flow phần 2 khi import helper.
- targeted-walkthrough.py / targeted-flow.json: xác minh cuối cho reviewer cần task.review, role tạo project thiếu project.view, grant role project vượt quyền, UUID member khác project, q=0 và validation tiếng Việt. Đây là kiểm tra HTTP trực tiếp, không giả API hoặc model.
- closed-project-walkthrough.py / closed-project-flow.json: project cancelled/completed chặn tạo/sửa/archive Task; mở lại project cho phép sửa.
- task-browser.png: Project “Review Phase 3 Browser” được tạo qua UI; tạo Task BROWSER-P3-001 rồi sửa tiêu đề và estimate thành 75 phút. browser-read-back.json là dữ liệu DB tương ứng.
- routes.json: xuất từ route:list --except-vendor --json sau phần 3.
- technical-checks.txt: lint source PHP và PHP đã compile từ Blade, Composer validate, route/config cache, Pint và whitespace checks.

## Kết quả quan sát

| Boundary/flow | Kết quả thực tế |
|---|---|
| Blade → project create/update → DB → show | Có project và creator membership; đổi tên đọc lại đúng |
| Project cha/con, scope/date validation | Chặn vòng cha/con, cha khác workspace và deadline trước ngày bắt đầu |
| Danh sách và detail theo quyền | Member workspace chưa vào project không thấy project trong list, detail 403; project con không thừa kế membership |
| Project member add/update/suspend/remove/rejoin | Membership được ghi; suspended/left bị chặn ngay; rejoin giữ UUID |
| Workspace suspension | Project membership active không vượt được suspension workspace |
| Role project | Chặn Owner, tự sửa và grant Manager vượt quyền delegate; DB role target giữ nguyên |
| Task create/update/assign | Task WORK/TODO đúng creator/scope; đổi metadata không mất phân công; assign trái quyền 403; người ngoài bị validation |
| Reviewer sau siết điều kiện | Owner có task.review được gán; cấp mới cho Viewer bị chặn, DB giữ Owner |
| UTC | Nhập 22/10/2026 10:30 Asia/Ho_Chi_Minh, DB lưu 03:30 UTC |
| Scope của route | Task của project khác, member của project khác hoặc task project qua namespace personal trả 404 |
| Validation/bảo mật | Mã trùng, estimate âm, field status lách workflow bị chặn; CSRF thiếu 419; HTML description được escape |
| Search/filter/sort/pagination | 13 task tạo qua HTTP; trang 1 có 10, trang 2 có 3; giữ query; filter urgent đúng task; q=0 đối chiếu SQL; sort lạ bị validation |
| Archive/restore | Ghi archived_at; archived task cấm edit; archived project cấm ghi task/member; restore hoạt động |
| Delete | Project không trống bị giữ; project draft trống xóa thật; TODO task trống xóa thật; task có dữ liệu lịch sử bị giữ |
| Task cá nhân | Tạo/sửa/xóa qua namespace personal, DB project_id=null |
| Browser thực | Tạo project, tạo/sửa task qua UI, screenshot và SQL đọc lại |
| Integrity cuối | PRAGMA foreign_key_check rỗng |

Canonical HTTP workspace: 01a14813-d974-73a4-af76-5894453b0855; project: 01a14813-e91e-739a-9846-cd8fef7cf1bc; task: 01a14814-12c7-7172-ba05-d8032074b35d. Các tài khoản phase3.*@example.test và dữ liệu review được giữ để duyệt. Cookie/private state tại storage/app/private/stage03-review, không commit hoặc công khai.

HTTP 200 trong log có thể là form sau validation redirect, phải đọc errors và SQL read-back; không coi mọi 200 là thao tác thành công. Các request raw_post vẫn dùng cookie/CSRF thật, một số back redirect về workspaces do Referer của script; form UI dùng URL trang thật.

## Lỗi phát hiện và sửa

Lần đầu thêm điều hướng workspace làm hai directive @endif/@can đứng liền nhau, render sau tạo workspace trả 500. Đã dừng tại boundary, tách directive, compile và lint PHP trong cache view, chạy lại flow chính thành công. Chỉ compile Blade chưa đủ chứng minh PHP sinh ra hợp lệ; đã bổ sung lint compiled views vào kiểm tra cuối.

Flow chính được ghi trước khi siết reviewer: khi đó Viewer có task.view được chọn làm reviewer. Sau rà soát đã yêu cầu task.review, tách reviewers khỏi people, chạy targeted flow đổi reviewer sang Owner và chặn cấp lại Viewer. targeted-flow.json và DB cuối là bằng chứng cho quy tắc mới; không sửa log cũ để giả kết quả. Script tái hiện chính đã cập nhật reviewer hợp lệ cho các lần chạy sau. Thông báo estimate/prohibited ban đầu còn tiếng Anh, targeted flow xác nhận bản tiếng Việt sau cập nhật.

## Giới hạn

Đã xác minh local SQLite, không phải MySQL/Redis/SMTP/production hoặc kiểm thử tải đồng thời. Không tuyên bố tree/state machine/review/dependency/blocker/activity tự động/file/progress của phần 4 đã chạy. Kiểm tra chống xóa lịch sử dùng dữ liệu fixture đã tồn tại, không tạo endpoint COMMENT giả. Script ghi dữ liệu .test thật; đọc nội dung trước khi chạy lại, không chạy trên production.

Không tìm được fixture chỉ có activity mà không có dữ liệu liên quan khác, nên nhánh chặn xóa chỉ do activity chưa được exercise riêng; log closed-project-flow.json ghi rõ bước bỏ qua này. Task có nhiều dữ liệu liên quan đã được xác minh giữ nguyên.

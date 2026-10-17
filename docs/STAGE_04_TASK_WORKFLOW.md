# Phần 4 — Cây Task, trạng thái/review, dependency/blocker và activity/file

17/10/2026. Phần 3 đã được chủ dự án duyệt. Phần 4 đã triển khai và xác minh local, dừng chờ duyệt; chưa sang phần 5. Không thêm test class/mocks, không reset DB, không đổi APP_KEY hoặc key tích hợp. Schema 20 bảng hiện có đủ cho phần này, không thêm migration.

## Luồng chung

Blade form → web route/session/CSRF → active user/workspace membership/scoped binding → TaskRequest hoặc TaskActionRequest → Controller → Policy → Service → transaction khóa workspace/task → Eloquent + HISTORY → redirect về trang Task. Phần ghi đọc lại quyền và trạng thái trong transaction; không tin nút ẩn/hiện trên FE.

## 1. Cây Task và tiến độ

- `TaskController::create()/edit()` cung cấp parents; TaskRequest nhận task_type GROUP/WORK/MILESTONE, parent_task_id, group_label, sort_order cùng metadata phần 3. Status, timestamps và workspace/project vẫn do server kiểm soát.
- `TaskService::save()` → `TaskTreeService::validate()` trong workspace lock: cha là GROUP chưa archived cùng workspace/project, không chu trình, tối đa 3 cấp (config/tasks.php). Khi chuyển cả nhánh, kiểm tra cả chiều cao nhánh bên dưới, không chỉ độ sâu node cha.
- WORK là lá, có estimate; GROUP/MILESTONE không estimate. GROUP không phân công trực tiếp. Chuyển loại bị chặn khi còn con/estimate/phân công, lịch/timer/dependency/blocker hoặc comment/file. Đổi loại/cha chỉ khi chưa bắt đầu; đổi tên/mô tả/thứ tự vẫn theo quyền update. Không âm thầm xóa estimate hay lịch để chuyển loại.
- Create/update thật ghi `TaskHistory::record()`; update không thay dữ liệu không tạo history thừa. New task có HISTORY tạo nên không còn đủ điều kiện xóa vật lý. Dùng archive để giữ dấu vết; các bản nháp legacy không lịch sử vẫn chịu điều kiện xóa cũ. UI nhận thêm deletable để ẩn thao tác xóa đã biết không hợp lệ; service vẫn kiểm tra đầy đủ.
- `TaskTreeService::sync()` duyệt GROUP từ dưới lên trong cùng transaction: rỗng/toàn TODO → TODO; tất cả con DONE/CANCELLED → DONE nếu có DONE, ngược lại CANCELLED; còn lại có con đã hoạt động → IN_PROGRESS. GROUP không có thao tác chuyển status trực tiếp, mỗi thay đổi tổng hợp có HISTORY group_synced.
- `TaskTreeService::progress()` chỉ đếm WORK lá không CANCELLED. Nếu mọi WORK có estimate dương: sum estimate DONE / sum estimate; nếu bất kỳ thiếu hoặc bằng 0: đếm WORK DONE / số WORK. Không cộng GROUP/MILESTONE, không trộn hai công thức. Archived WORK vẫn thuộc phạm vi tính; không cho archive làm tăng tiến độ giả. Không có WORK trả 0.
- `TaskWorkflowController::tree()` cung cấp nodes đã group theo parent, tasks và progress cho Blade tree/tree-node. Có cùng flow cho project và personal. Project completion chỉ xét WORK/MILESTONE chưa DONE/CANCELLED; GROUP là container, không chặn hoàn tất vì nhóm rỗng.
- Archive GROUP phải xử lý con trước; restore từ cha xuống con. Policy chặn khôi phục con khi cha còn archived. Không đổi trạng thái workflow chỉ vì archive.

## 2. State machine và review qua các hàm

`TaskWorkflowController::status(TaskActionRequest)` → `TaskWorkflowService::status()` → `locked()` khóa workspace/task, kiểm tra quyền và đọc lại current status.

| Từ | Đến được phép | Điều kiện quyền/chính |
|---|---|---|
| TODO | IN_PROGRESS, CANCELLED | Người thực hiện hoặc người có task.assign, đều cần task.update |
| IN_PROGRESS | REVIEW, CANCELLED | Quyền thực hiện; nộp duyệt cần reviewer, ngoại lệ Owner workspace |
| REVIEW | DONE, IN_PROGRESS | Người duyệt được chỉ định có task.review, hoặc Owner workspace |
| REVIEW | CANCELLED | Quyền thực hiện, có lý do |
| DONE | TODO | Quyền review, có lý do, không có successor đã bắt đầu |
| CANCELLED | TODO | Quyền thực hiện, có lý do |

`TaskPolicy::execute()` yêu cầu Task mutable + task.update và assignee hoặc task.assign. `review()` yêu cầu mutable + task.review và đúng reviewer hoặc Owner workspace. Owner có ngoại lệ quản trị kể cả Task chưa phân công; không biến mọi Manager thành người duyệt của mọi Task. User/membership phải còn active.

Form phải gửi expected_status từ server. Nếu trạng thái đã đổi, trả validation yêu cầu tải lại. Hủy, mở lại và yêu cầu sửa cần reason. Không có đường TODO → DONE. Trạng thái giữ nguyên là no-op, không thêm history.

IN_PROGRESS/REVIEW/DONE đều kiểm tra blocker open và predecessor FS DONE + completed_at + lag đã qua. Có timer đang chạy thì chặn chuyển trạng thái. Reopen/cancel prerequisite bị chặn nếu successor khác TODO/CANCELLED. Không bỏ qua blocker bằng nút quản trị; phải resolve và ghi history trước.

started_at ghi lần bắt đầu, giữ khi yêu cầu sửa; DONE ghi completed_at. Mở lại TODO xóa started_at/completed_at của lần thực hiện; lần trước còn HISTORY. Metadata WORK/MILESTONE ở REVIEW/DONE/CANCELLED bị khóa; phải reject/reopen đúng flow trước. Comment/file vẫn cho phép trên DONE nếu project chưa đóng/archived và actor đủ quyền.

## 3. Dependency

`TaskWorkflowController::dependency()` → `TaskWorkflowService::dependency()` trong lock → policy link (task.assign) → chỉ successor WORK/MILESTONE TODO; predecessor WORK/MILESTONE cùng scope, chưa archived → tạo FS với lag_minutes 0..525600. Observer chặn tự nối và chu trình; unique DB chống trùng.

`removeDependency()` chỉ gỡ incoming edge thuộc đúng task khi successor TODO; ghi history task.dependency_removed. Không nhận loại dependency tùy ý từ client, MVP là FS. Predecessor CANCELLED không thỏa FS; phải xử lý lại kế hoạch/dependency theo quyền. Một phút chờ đã được kiểm tra với thời gian trôi thật, không sửa đồng hồ/DB timestamp.

## 4. Blocker

`TaskWorkflowController::blocker()` → `TaskWorkflowService::blocker()` → policy block (task.update, Task mutable). WORK/MILESTONE chưa đóng mới report/resolve/reopen. Report lưu reported_by từ session và reason, status=open. Resolve/reopen chỉ người báo hoặc người có task.assign hoặc người có quyền review Task; không dùng giá trị actor/resolved_by từ form.

Resolve ghi resolved_by/resolved_at; reopen xóa các mốc resolve, giữ lý do gốc và reported_by. Không xóa blocker để che lịch sử. ID blocker phải truy vấn từ task hiện tại. Mọi thay đổi thật ghi HISTORY.

## 5. Comment và HISTORY

`TaskWorkflowController::comment()` → `TaskWorkflowService::comment()` → policy comment. Tạo COMMENT từ content, type/source/actor do server ghi; payload giả type=HISTORY/source=ai/actor khác bị bỏ qua. Tác giả được sửa, tác giả hoặc người có task.assign được xóa mềm. Người khác không sửa nội dung tác giả.

Service luôn truy vấn type=COMMENT trong task hiện tại; đưa HISTORY ID vào URL sửa/xóa COMMENT trả 404. Observer nền tiếp tục chặn sửa/xóa HISTORY qua Eloquent. Không có route ghi/sửa HISTORY cho người dùng. Nội dung Blade escape; activity paginate 15 theo created_at + id, có filter COMMENT/HISTORY/ATTACHMENT, giữ query khi đổi trang.

TaskHistory ghi sự kiện create/update/status/group/archive/restore/dependency/blocker/comment/file. Diff metadata lưu before/after; trạng thái có from/to/reason. History ghi cùng SQL transaction, không công bố thành công nếu transaction lỗi. Đây là nhật ký nghiệp vụ, không suy ra thời gian actual từ status.

## 6. File riêng tư

`TaskWorkflowController::upload()` → TaskActionRequest → `AttachmentFormat` → `TaskAttachmentService::store()`.

- Giới hạn app 5120 KB, cho TXT/CSV/PDF/PNG/JPEG/DOCX/XLSX. Kiểm tra MIME theo đúng phần mở rộng, không chỉ hai danh sách rời. Office cần ZIP, kiểm tra container có [Content_Types].xml và word/document.xml hoặc xl/workbook.xml, tối đa 512 entries, không giải nén.
- Gate attach trước ghi file và kiểm tra lại trong SQL lock. Tên người dùng được sanitize/basename, path dùng Task UUID + filename UUID ngẫu nhiên, disk task_files private. Lưu ATTACHMENT metadata gồm tên, MIME, size, SHA256, disk/path; không đưa path/disk thô vào HTML.
- File ghi xong mới lưu metadata/HISTORY trong transaction. Nếu SQL thất bại, service xóa file vừa ghi rồi ném lại lỗi. Chưa mô phỏng sự cố ổ đĩa/SQL để nghiệm thu compensation.
- `download()` kiểm tra task.view hiện tại, row ATTACHMENT thuộc task và chưa soft-delete, disk/path hợp lệ, bytes tồn tại → download attachment với nosniff và private/no-store. Không tải bằng URL public/storage. Đã tắt serve của các local private disk; route public private-file trả 404.
- `remove()` yêu cầu task.attach và tác giả upload hoặc task.assign → soft-delete ATTACHMENT + HISTORY. File bytes vẫn private để giữ audit, không còn download qua route. Chưa có UI restore/purge hoặc job dọn file; không gọi thao tác này là xóa vật lý.
- Đây là kiểm tra loại/container, không phải parse đầy đủ hoặc render Office, không tuyên bố quét malware. TXT/PNG/OOXML fixture đã được đối chiếu bytes/hash qua HTTP thực; các định dạng còn lại có rule nhưng chưa được exercise riêng.

## 7. Chạy local và bàn giao

PHP ZIP có sẵn trên máy nhưng chưa bật mặc định. Server review dùng `php -d extension=zip -d upload_max_filesize=6M -d post_max_size=7M -S ...` với router Laravel. Có helper `pwsh -File scripts/serve-local.ps1`; không thay php.ini toàn máy. Nếu ZIP đã bật trong php.ini, bỏ flag extension trùng. Production cần cấu hình ZIP/upload limits tương ứng.

Blade đã nối: tasks/_form, show, workflow, tree, tree-node; textarea nhận id riêng để tránh trùng reason giữa hai form. Layout lỗi 404 được sửa để render được ngoài web middleware khi chưa có $errors. File bàn giao FE có route, field, variable và quyền thực tế. FE tiếp tục thiết kế giao diện; không tự cài state machine trong JavaScript.

Minh chứng tại docs/verification/stage04/README.md. Môi trường đã kiểm tra SQLite local, chưa kiểm tra MySQL/production/tải đồng thời, timer UI, scanner hoặc tích hợp ngoài. Phần 5 lịch/settings/thông báo chưa được triển khai tiếp. Dừng chờ duyệt phần 4.

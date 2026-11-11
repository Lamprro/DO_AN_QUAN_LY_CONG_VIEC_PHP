# Tổng hợp công việc trong workspace cá nhân — 11/11/2026

Đã áp dụng vào source `D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP`.
Nền trước thay đổi: `c72ed7e`, nhánh `main`; Git sạch trước khi sửa. Không dùng bản code lịch sử trong thư mục chat.

## Hành vi sau thay đổi

- Trang công việc của workspace cá nhân do người đăng nhập sở hữu gồm: việc trực tiếp không gắn project tại workspace đó và WORK/MILESTONE được giao cho người đó trong các project còn quyền xem.
- Owner không nhìn thấy việc được giao cho người khác chỉ vì mình sở hữu workspace. Các bộ lọc chỉ thu hẹp phạm vi tổng hợp.
- Project giữ danh sách chung theo quyền và bộ lọc **Việc của tôi** hiện có, theo xác nhận của người dùng. Không đổi project thành danh sách chỉ assignee.
- Không copy, di chuyển hay tạo bản đại diện task. Danh sách, Kanban và cây dùng cùng truy vấn phạm vi đọc. Số công việc ở tổng quan workspace cá nhân dùng cùng phạm vi, tính cả task lưu trữ như bộ đếm cũ.
- Hiển thị workspace/project nguồn. Mọi liên kết xem, sửa, panel, đổi trạng thái và URL kéo thả Kanban lấy từ nguồn của từng task. Giữ nguyên controller, policy, transaction, kiểm revision/status và bất biến nguồn của đường ghi hiện có.
- Form tạo mới ở trang tổng hợp vẫn tạo việc trực tiếp tại workspace cá nhân; không cho chọn nguồn tùy ý qua payload.
- Cập nhật hàng loạt ở trang cá nhân chỉ cho chọn việc trực tiếp tại đó. Việc project có nút sửa riêng; có thể mở project để chọn hàng loạt cùng nguồn. Gửi thủ công một tập ID lẫn nguồn bị từ chối toàn bộ.
- Cây giữ cấu trúc việc riêng. Task project được giao cho mình được đưa lên gốc hiển thị nếu cha không có trong tập kết quả; không tải thêm GROUP/sibling của người khác. Không thay parent_task_id trong DB. Tiến độ tính trên các WORK được chọn.
- Preset **Chờ tôi duyệt** kiểm quyền và trạng thái project nguồn; quyền Owner tại workspace cá nhân không trở thành quyền duyệt ở project khác.

## Quyền, lưu trữ và workspace mặc định

Tái sử dụng `WorkspaceAccess`, `ProjectAccess`, `TaskAccess`. Tài khoản, thành viên workspace/project bị dừng hoặc vai trò thiếu `project.view`/`task.view` không còn cung cấp task cho tổng hợp. Tải lại trang sẽ áp dụng quyền mới; thao tác từ trang cũ vẫn bị controller/service kiểm lại.

Theo luật hiện hành, project đã lưu trữ/hoàn tất/hủy vẫn có thể đọc khi còn quyền; chỉnh sửa bị khóa. Thay đổi này không tự biến lưu trữ project thành thu hồi quyền xem. Bộ lọc lưu trữ ở danh sách/Kanban lọc `tasks.archived_at`.

Đã kiểm tra luồng đăng ký thật tạo đúng một workspace personal, đăng ký trùng email không tạo thêm. API tạo workspace thường luôn tạo team; update chỉ nhận tên/múi giờ. Service hiện chặn xóa/chuyển chủ/rời workspace personal. Không cần thay provisioning hoặc migration. Schema hiện chưa có unique constraint owner+personal và service nội bộ `create(..., true)` không idempotent; không suy rộng kết quả đăng ký thành bảo đảm chống gọi trực tiếp service sai mục đích.

## Xác minh đã thực hiện

Chạy source thực với SQLite riêng `output/personal-task-overview-2026-11-11/review.sqlite`, file môi trường/cache/session/view/log riêng. Không dùng Laravel TestCase, không mock DB, không dùng test mặc định làm bằng chứng. Fixture ban đầu: 3 user, 6 workspace, 8 project, 28 task; sau các flow tạo việc/đăng ký: 4 user, 7 workspace, 29 task. Mail dùng array transport; AI tắt; không gửi thư hoặc gọi provider ngoài.

| Luồng | Kết quả |
|---|---|
| Danh sách, phân trang 10 dòng, Kanban 10 thẻ/cột, cây, nhãn nguồn | Đúng tập ID, không lẫn task của người khác hoặc nguồn không còn quyền |
| Project chung và preset mine | Giữ nguyên phạm vi cũ |
| Search, overdue, review, archive, assignee, saved filter | Đúng phạm vi; không dùng filter để mở rộng quyền |
| Panel/full detail/edit tại nguồn, revision cũ, status cũ | Xem/sửa nguồn thành công; revision cũ 409, status cũ 422 |
| URL cá nhân giả cho task project | 404 ở xem/sửa/đổi trạng thái |
| Viewer/project archived | Xem được; sửa/chuyển trạng thái 403 |
| Bulk lẫn nguồn / bulk trực tiếp cá nhân | Lẫn nguồn 404 và không ghi một phần; bulk cùng nguồn thành công |
| Mất membership project/workspace, mất task.view sau khi mở trang | List/board/tree loại task; panel và ghi từ URL cũ 403 |
| Đổi assignee | Task rời tổng hợp cá nhân, vẫn nằm trong danh sách chung project |
| Disable account | Trang công việc trả 403 theo middleware hiện hành |
| Đăng ký, đăng ký trùng, bảo vệ workspace personal | Tạo một personal; trùng email 422; xóa/chuyển chủ/rời bị chặn |
| Đọc lại DB | Task nguồn giữ ID/workspace_id/project_id; không copy/move/delete; FK check rỗng |
| Chrome thật | Đăng nhập → personal → sửa task project qua panel → danh sách cập nhật; Kanban kéo PAGE-1 từ TODO sang IN_PROGRESS, hiện thông báo thành công và đọc lại DB đúng |
| PHP syntax / Laravel Pint / git diff --check | Đạt |

Hai lượt xác minh hoàn tất: **86/86 điểm xác nhận, 75 yêu cầu HTTP** (53/53 qua 48 HTTP và 33/33 qua 27 HTTP), cộng thao tác Chrome. Số này không cộng lượt thử dở. Một lượt kiểm tra ban đầu đặt kỳ vọng tài khoản disabled trả 302, thực tế middleware trả 403; đã sửa kỳ vọng và chạy lại thành công. Việc chuẩn bị môi trường review từng bị giới hạn quyền ghi cache của tiến trình PHP; chạy lại bằng quyền thực thi được duyệt, vẫn dùng DB review có kiểm đường dẫn.

Bằng chứng và script tái hiện lưu tại:
`C:\Users\maiva\.codex\.chatgpt-projects\g-p-6ac9f1ab7ff88191b43033b8bc45d9d6\output\personal-task-overview-2026-11-11`

- `http-results.json`, `revocation-results.json`: kết quả hoàn tất; `attempt-revocation-status-expectation.json`: lượt thử dở, không cộng vào tổng đạt.
- `review_setup.php`: migrate DB riêng có chặn nhầm DB và tạo fixture qua service/Eloquent.
- `review_http.py`: cookie/CSRF → route/controller/service thật → đọc lại SQLite.
- `review_control.php`, `review_revoke.py`: điều chỉnh fixture bằng Eloquent/service để kiểm thu hồi quyền, gọi HTTP và khôi phục fixture trong finally.
- `fixture.json`: ID nguồn của dữ liệu review. `review_bootstrap.php`, `review_router.php`: chạy source thật trên storage/DB riêng; không thêm route nghiệp vụ giả.

## File triển khai

- Mới: `app/Services/Task/TaskListingQuery.php`.
- Sửa: `app/Services/Task/TaskPageData.php`, `app/Services/Workspace/WorkspaceQuery.php`.
- Sửa Blade: `tasks/index`, `tasks/board`, `tasks/tree`, `tasks/tree-node`, `tasks/_filters`, `tasks/_utilities`, `tasks/panel`, `workspaces/show`.
- Mới Blade: `tasks/_source`, `tasks/_overview-note`.
- Nhật ký bàn giao: tài liệu này. Không sửa JS vì panel đã dùng href/form action nguồn và Kanban đã dùng data-status-url riêng của từng thẻ.

## Giới hạn và đánh giá

Phù hợp Laravel MVC/Blade: phạm vi đọc nằm ở Query, PageData chuẩn bị dữ liệu, Blade hiển thị, đường ghi vẫn do service/policy nguồn quyết định. Không có migration hoặc thay đổi dữ liệu chính/APP_KEY chính. Không sửa Google Sheet hay triển khai production.

Đã kiểm tra trên SQLite review và Chrome desktop. Chưa kiểm MySQL/MariaDB, mobile hoặc tải lớn. Việc xác định project được phép hiện duyệt các workspace/project khả dụng theo các lớp quyền sẵn có; chi phí tăng theo số project. Cây vẫn không phân trang và vẫn tính cả task lưu trữ như trước. Các module lịch, kế hoạch, dashboard/báo cáo không được mở rộng thành tổng hợp xuyên project trong đợt này.

## Git và bước phát hành

Remote `origin` (fetch/push) xác minh được là `https://Lamprro@github.com/Lamprro/DO_AN_QUAN_LY_CONG_VIEC_PHP.git`; repo hiển thị: https://github.com/Lamprro/DO_AN_QUAN_LY_CONG_VIEC_PHP. Nhánh `main`, tracking `origin/main`. Đã fetch `origin main` trước bước commit; HEAD và remote main cùng ở `c72ed7e`, không có thay đổi cần tích hợp.

Sau khi xác minh kho đích, người dùng đã xác nhận commit và push trực tiếp `main`, không force push, không tạo PR hay deploy. Commit message: `Aggregate assigned tasks in personal workspace`. Kết quả push và mã commit được báo riêng sau khi đối chiếu với remote main.

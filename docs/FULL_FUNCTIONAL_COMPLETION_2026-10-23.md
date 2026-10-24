# Hoàn thiện chức năng F01–F15 — 23/10/2026

Đợt này đã triển khai bốn nhóm trước đây còn backlog: F11 lịch nhóm có đồng ý của người nhận, F12 giờ làm tùy chỉnh, F13 watcher/mention và F14 mẫu/công việc lặp. Giữ Laravel MVC/Blade; không thêm lớp test hoặc dữ liệu thành công giả. F13 chọn watcher/mention với một assignee chính, đúng phương án “hoặc” của kế hoạch gốc.

## Trạng thái phát hành chính xác

- Mã nguồn và bản review đã có 35 bảng: 27 nghiệp vụ + 8 hạ tầng. Database chính vẫn 31 bảng; migration bốn bảng mới **chưa áp dụng** do kiểm duyệt tự động yêu cầu xác nhận riêng. Không được ghi rằng tính năng mới đã hoạt động trên database chính.
- Migration `2026_10_23_180000_add_collaboration_and_templates.php` chỉ tạo calendar_proposals, task_watchers, task_templates, task_template_runs. Có bản sao lưu trước thay đổi trong private storage. Không reset, rollback hoặc xóa dữ liệu cũ.
- Các trang cũ tương thích schema cũ. Trang phụ thuộc bảng mới trả 503 có hướng dẫn nâng cấp; preflight báo thiếu bảng. Chỉ chạy migration chính sau xác nhận đang chờ.
- Dịch vụ ngoài cần key/cấu hình theo yêu cầu đã hoãn: AI, GitHub webhook, SMTP và hạ tầng production. Chưa chứng minh provider/network thật, HTTPS công khai, tải production hoặc MySQL. Email/Telegram/Google Calendar không thuộc tích hợp được chọn trong DELIVERY_STATUS.

## Đối chiếu phạm vi

| Nhóm | Đã có và cách xử lý |
|---|---|
| Auth / workspace / role / member | Session, CSRF, recovery/verification, membership hai cấp, Policy theo quyền hiện tại; invitation và chuyển chủ theo quy tắc cũ |
| Project / Task | CRUD, cây GROUP/WORK, phân trang/lọc/tìm, assignee/reviewer, status/review, FS/lag/blocker, activity/private file |
| Calendar / Timeline / Settings | UTC lưu trữ, timezone hiển thị, xung đột, revision; settings không xóa lịch làm tùy chỉnh |
| Dashboard / report / queue / AI / Plan | Snapshot/CSV/print, worker và vận hành, AI request có lỗi thật khi disabled; Plan version/diff/preview/approve/apply, local schedule, impact, timer |
| F01–F03 | Revision và form sửa nhanh, diff/preview, editor kế hoạch: giữ flow cũ đã nghiệm thu |
| F04–F10 | Preset/bộ lọc riêng, bulk nguyên tử, nhắc việc, hạn, vận hành, sửa giờ có lịch sử: giữ flow cũ |
| F11 | Gửi đề xuất → người nhận đồng ý/từ chối; chỉ accept mới tạo lịch thật. Kiểm quyền và xung đột lại khi accept; private/task scope được lọc cả list/count/inbox |
| F12 | Ngày làm, tối đa 4 ca/ngày, slot và timezone cá nhân; chống chồng ca/ghi đè form cũ; Plan cũ giữ snapshot |
| F13 | Tự theo dõi hoặc quản lý watcher theo quyền; mention hợp lệ trong comment; không thông báo người mất quyền và không đếm trùng assignee |
| F14 | Mẫu từ tối đa 50 task; cây/FS ánh xạ UUID mới. Tạo thủ công hoặc ngày/tuần/tháng; pause/resume/archive, lịch sử chạy, chống trùng kỳ và rollback nguyên tử |
| F15 | Tìm xuyên project theo scope/quyền, không lộ tiêu đề hoặc count ngoài quyền |

## Flow qua các hàm

**F11:** TeamCalendarController::store → TeamCalendarService::propose kiểm actor, project, người nhận, task/assignee, giờ và visibility → lưu pending + NotificationService::direct. TeamCalendarController::respond → TeamCalendarService::respond khóa người nhận/workspace/proposal, đối chiếu revision/quyền hiện tại → CalendarService::save khi accept; decline không tạo event; cancel dùng đúng event gốc qua CalendarService::save(status=cancelled). Notification inbox dẫn đến proposal cụ thể. Người gửi chỉ rút đề xuất pending; người nhận có thể hủy lịch đã nhận.

**F12:** WorkHoursController::update → WorkHoursService::save validate/khóa user/version rồi merge user_settings. WorkHoursService::values lấy override hoặc mặc định theo workspace. PlanService ghi snapshot/fingerprint; PlanScheduleService::suggest dùng ngày/ca/slot/timezone để chọn khoảng rảnh. Lịch làm mới không làm Plan cũ đổi cách hiểu; DST không hợp lệ/giờ lặp được bỏ qua.

**F13:** TaskController::show → TaskCollaborationService::viewData cung cấp dữ liệu cho Blade. TaskCollaborationController → TaskCollaborationService::watch kiểm scope và quyền tự theo dõi/quản lý người khác. Comment → TaskActionRequest → TaskWorkflowService::comment → TaskCollaborationService::mentions; validation lỗi rollback comment. NotificationService kiểm membership/Policy/preference hiện tại, dedupe theo activity/người nhận. Tối đa 100 watcher và 20 mention/comment.

**F14:** TaskTemplateController::store → TaskTemplateService::create snapshot toàn cây/phụ thuộc được chọn, không sinh task ngay. instantiate → khóa cache riêng template → instantiateLocked → transaction → TaskService::save tạo UUID/code/TODO mới và TaskWorkflowService::dependency ánh xạ FS. Không clone DONE, actual, file, comment hoặc lịch sử. Unique(template, occurrence_key) chống trùng; request_key UUID chống double submit. Definition và lịch sử run bất biến; cấu hình sửa bằng revision.

Scheduler mỗi phút → tasks:generate-recurring → TaskTemplateService::due → khóa toàn đợt + từng template; tối đa 20 mẫu/đợt. Kiểm lại quyền creator/assignee/project; lỗi rollback cả bộ task/history, ghi failed run, chuyển kỳ tương lai. Kỳ lỡ chỉ tạo một bộ, không bù hàng loạt. Hàng tháng giữ ngày neo (31/1 → 28/2 → 31/3); bỏ giờ DST không tồn tại hoặc lặp. Pause/resume/archive giữ lịch sử, không xóa task đã tạo.

## Các lỗi tìm thấy và đã sửa

1. Hủy lời mời đã nhận tạo nhầm một event khác: truyền đúng event hiện hữu vào CalendarService; SQL xác nhận chỉ một event.
2. Người chỉ có quyền calendar có thể nhìn proposal gắn task ngoài scope: lọc SQL trước phân trang/count và kiểm lại inbox; meeting không task vẫn được dùng.
3. Hai PHP process tạo task lặp tranh chấp SQLite: thêm cache lock cấp đợt và template, unique kỳ và transaction. Chạy lại hai process thực cho đúng một run và ba task.
4. Xóa project có proposal/template gặp FK lỗi: chặn ở service với lỗi form rõ, giữ lịch sử.
5. Thành viên lịch rời nhóm làm không hủy được event: cho giữ nguyên danh sách lịch sử khi hủy; vẫn cấm thêm người không hợp lệ.
6. Settings chung làm mất work_hours; Blade tự truy vấn; menu mất project context; run history lộ mã kỹ thuật: đã merge JSON, chuyển data chuẩn bị vào controller/service, bổ sung context và nhãn/liên kết task dễ hiểu.
7. Schema cũ gây lỗi 500 sau cập nhật code: bổ sung middleware 503 và guard phần watcher/preflight, không tự nâng cấp database chính.

## Minh chứng và giới hạn

Có 13 flow hoàn tất, 165 điểm xác nhận và 384 lượt HTTP được ghi. Đây là số điểm xác nhận/log của các kịch bản đã chạy, không phải tỷ lệ bao phủ hay cam kết “không còn lỗi”. Xem [minh chứng](verification/full-function-2026-10-23/README.md), các attempt được giữ riêng và không tính đạt. Đã lint/Pint PHP thay đổi, compile route/Blade; backup/restore đủ 35 bảng và private files qua HTTP, kiểm queue/scheduler/preflight, hồi quy task/workflow/calendar/plan/report. Browser kiểm desktop 1366×900 và mobile 390×844 không tràn ngang ở hai trang minh chứng, không thấy console error trong lần quan sát này; chưa là audit accessibility toàn diện.

## Cần làm trước production

1. Chấp thuận và áp dụng migration bốn bảng mới trên database chính đã backup; xác minh dữ liệu cũ và key không đổi. Đây là bước còn chặn kích hoạt đầy đủ trên môi trường chính.
2. Cấu hình APP_ENV=production, DEBUG=false, HTTPS/domain/trusted hosts/proxies, cookie secure và mail thật; không đưa .env hoặc key vào Git/gói source.
3. Chạy scheduler/worker bền vững bằng service và kiểm heartbeat. Cache lock phải dùng chung giữa mọi worker/node; local file cache chỉ phù hợp một host. Nếu nhiều host cần Redis/database cache chung được cấu hình và diễn tập lại.
4. Diễn tập restore/tải/gián đoạn worker trên hạ tầng đích; bản hiện tại mới chứng minh Windows/PHP/SQLite local và restore riêng. Không coi một command pass là nghiệm thu production hoàn tất.
5. Nối/kiểm provider AI và webhook GitHub thật sau khi có key. Giữ disabled rõ ràng khi chưa cấu hình; không giả kết quả.

Không commit/push trong đợt này vì yêu cầu hiện tại là hoàn thiện và rà soát. Các báo cáo trước giữ làm lịch sử; tài liệu này thay trạng thái F11–F14 backlog của các bản trước.

## Kết quả đóng vòng bàn giao

Gói source được giải nén vào thư mục/database riêng, dùng vendor đã cài trên máy (không tuyên bố composer download mới). Setup --demo chạy hai lần giữ nguyên env/key, có35bảng/5users/40tasks, foreign key sạch. Preflight local, config/route/Blade cache và đăng ký recurring scheduler đạt; HTTP thực mở giờ làm, lịch nhóm và template trong scope project trả200. Xem fresh-source-install.json. Smoke fixture ban đầu sửa tên biến helper và bổ sung project_id theo guard sẵn có; không bỏ guard ứng dụng để làm kiểm tra đạt.

schema-compatibility.json kiểm trên bản sao riêng của DB31bảng: task detail200 và trang mới503 có hướng dẫn migration. main-preservation.json xác nhận31bảng vàenv/key chính giữ nguyên baseline. Composer validate --strict, platform requirements và audit đạt (0advisory tại thời điểm kiểm); không đổi dependencies. Source-security-audit.json ghi quét source/ZIP/key thực tế không in secret, không phát hiện dữ liệu cấm. Đây là kết quả tại thời điểm kiểm, không thay thế việc cập nhật dependency và giám sát sau phát hành.

Ảnh giao diện: [Mẫu công việc desktop](verification/full-function-2026-10-23/full-template-desktop.png), [Giờ làm mobile](verification/full-function-2026-10-23/full-hours-mobile.png). Gói nguồn: ../dist/work-management-mvc-source.zip, manifest kèm SHA256 từng file. Gói không có vendor/.env/database thật/private files; máy nhận cần cài dependency và cấu hình riêng.

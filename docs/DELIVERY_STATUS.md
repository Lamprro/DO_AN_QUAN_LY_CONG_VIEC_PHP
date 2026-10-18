# Bàn giao triển khai toàn bộ — 17/10/2026

**18/10/2026 — Đợt D/P1 quan sát đã triển khai.** Xem [P1_OBSERVATION.md](P1_OBSERVATION.md) và bàn giao FE mới nhất. P0 giữ nguyên; đợt E/F04–F10 và P2 còn là backlog. Các mô tả “chưa có/cần bổ sung” của bản kế hoạch gốc là lịch sử trước nâng cấp.

**Cập nhật P0 17/10/2026:** đã có app shell, forms/mobile, workflow/timer, F01 edit revisions, F03 visual plan editor và F02 diff/preview. Đọc [P0_IMPLEMENTATION.md](P0_IMPLEMENTATION.md) và hợp đồng P0 tại đầu FRONTEND_HANDOFF.md. Các đoạn trước nâng cấp bên dưới là nhật ký.

Chỉ đạo mới: hoàn thiện các phần còn lại, không dừng chờ duyệt từng phần; kiểm tra bằng flow/dữ liệu thật, không thêm TestCase/mocks. Tài liệu này ghi trạng thái hiện tại, thay các câu “chờ duyệt/chưa làm phần7” trong nhật ký phần1–6 vốn được viết trước chỉ đạo mới.

Đã có backend MVC/Blade: Auth/profile; workspace/role/member/invitation; project/project member; task CRUD/cây/tiến độ; status/review/FS/lag/blocker; comment/private file/history; Calendar/Timeline/timezone/conflict; settings/in-app notifications; dashboard/report snapshot/CSV/print; AiRun/AiCall/database queue; Plan draft/version/propose/approve/reject/apply; local scheduling/impact; timer thực tế; GitHub signed webhook logs; hướng dẫn cài, FE và hồ sơ minh chứng.

Phần 7: PlanController::store → PlanService::create/validate/fingerprint chỉ lưu draft. Sửa tạo version mới, parent cùng group. action gọi transition, kiểm quyền theo plan.update/approve/apply. Apply cần cả plan.apply và plan.approve; kiểm snapshot, active base, task/member/calendar và lịch sử; dùng TaskService, TaskWorkflowService, CalendarService trong một transaction. Một lỗi rollback toàn bộ. Unique active_group_key + optimistic expected_status + applied_at chống apply trùng. Bản đã áp dụng không được sửa payload.

Phần 8: PlanService::impact đọc quá hạn/open blocker/dependency chờ; không sửa trạng thái. PlanScheduleService::suggest tạo version local theo config giờ làm, estimate, FS, busy; chỉ lịch chính người gửi. PlanningAiController → AiRunService::requestPlanning → ProcessAiRun → HttpAiProvider → PlanningContext::result validate/create draft. Replanning cần base; assistance chỉ tư vấn. TimeTrackingService::start/stop ghi actual thật, chống hai timer cùng người, chỉ assignee đang IN_PROGRESS được bắt đầu; chủ timer vẫn dừng được sau mất membership mà không lộ task. GitHub là tích hợp tùy chọn duy nhất được chọn: nhận push/ping ký HMAC, check repo/connection/current permissions, idempotent delivery, liên kết [CODE] vào HISTORY; không thay task status.

Phần 9: có kiểm tra HTTP/session/CSRF, SQL read-back, file, browser và worker từ các phần1–6; bổ sung docs/verification/final. Có kiểm tra cài vào thư mục/database riêng từ source đóng gói trên máy hiện tại. Không chạy migrate:fresh/reset trên database đang dùng. Không tuyên bố đã cài trên một máy vật lý thứ hai hoặc chạy MariaDB/MySQL server thật.

Phần 10: tài liệu cài/vận hành, hợp đồng FE/plan/AI, ma trận minh chứng, kịch bản demo và khung nhật ký đóng góp. Có gói source ZIP/manifest ở dist (không .env/key/database live/vendor/cookies/private files); cần composer install trên máy nhận. Các hoạt động do con người thực hiện như phân công tên thật, đóng góp từng thành viên, quay video và bảo vệ không được ghi là đã làm thay.

Các phần cần cấu hình sau theo yêu cầu chủ dự án: nhà cung cấp AI/HTTPS bridge/key/model; Redis production cho TTL invitations; SMTP nếu muốn gửi mail; webhook GitHub public HTTPS/repository/secret. AI_ENABLED và INTEGRATIONS_ENABLED vẫn false trên môi trường chính; chưa kiểm tra thành công với provider AI hoặc GitHub network thật. Việc thiếu key không được biến thành kết quả AI giả. Có thể chạy trọn lifecycle bằng draft nhập dữ liệu/local schedule.

Giới hạn nghiệp vụ có chủ ý: apply chỉ sửa task chưa bắt đầu, không xóa dữ liệu cũ khi bỏ dòng khỏi JSON; slot chỉ chính người apply; scheduler local không tối ưu đa nhân sự; so sánh phiên bản nay có diff trường trước/sau, source editor và preview dùng chung đường apply. Report hiện chỉ chủ snapshot xem với quyền nguồn hiện tại. Email/Telegram/Google Calendar không nằm trong tích hợp được chọn và không có trạng thái thành công giả.

Xem DEPLOYMENT.md để cài/vận hành, FRONTEND_HANDOFF.md để FE tiếp tục, PLAN_PAYLOAD.md và AI_PROVIDER_CONTRACT.md để nối logic. Dữ liệu review còn trên máy để xem lại, không đưa DB hiện tại vào gói source.

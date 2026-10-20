# Kiểm chứng sau bàn giao FE — 20/10/2026

Nguồn chính D:/HeThong_PhanTichTaiChinh/DO_AN_QUAN_LY_CONG_VIEC_PHP. Giữ đầu vào FE55file khác HEAD7f0867d. Database chính được sao lưu riêng trước kiểm; không reset, không chạy migration fresh, không xóa task/lịch sử đang dùng. Không thêm lớp test hoặc mock. [Đánh giá và flow](../../POST_FE_REVIEW.md).

| File | Bằng chứng |
|---|---|
| main-pages.json | Đọc35 trang/module chính, thêm show/preview fixture bằng session thật trên DB chính. HTTP200 là chứng minh render, không tự chứng minh các nút hoạt động. |
| release-install.json | Cài source vào temp/database tách biệt, migration đầy đủ, demo seed2lần giữ key và40task, login/trang thật. Vendor dùng lại trên cùng máy, không phải máy vật lý thứ2. |
| flow-core.json | Tạo workspace/project12task; board10+2; source khác workspace không lộ title/id/count; wildcard literal và escape script; saved board filter whitelist; bulk success/stale rollback; blocker, FS, timer thật chờ3giây, nộp duyệt/hoàn thành, comment escape. |
| flow-calendar-plan.json | Calendar A/Bversion stale và gửi lại đúng body lỗi; fresh cho sửa mới; busy conflict rollback; draft/preview rollback/propose/approve/apply/replay; fixture thiếu payload không trả valid; HTTPempty plan không ghi. |
| flow-reports-queue.json | Correction từ timer thật với audit, hai session stale/input giữ; snapshot cũ byte-identical, snapshot mới actual giảm tương ứng, CSV/print; worker AI disabled failed; nhắc hạn in-app sent/dedupe, operations permission. |
| flow-admin.json | fresh task/project/calendar bỏ query một lần, invalid forms giữ input; rolecreate/edit/delete unused; invitation file-cache/local log mail, accept, project membership; revoke trước worker→skipped/no reminder, search không còn quyền; private busy redaction; multipart TXT thật và download bytes bằng nhau, unauthorized403; settings lưu thật. Token invitation được redacted, cookie/private-state không đưa vào source. |
| technical.json | PHP Pint/lint, node syntax, compiled Blade lint, route/config cache rồi clear, scheduler; hash/counter task/project/plan/calendar/time/history/membership main giữ nguyên, env/APP_KEY hash giữ, flags AI/integrations false, FK sạch. |

Source review tại temp wm-release-w4apgton/project, app URL8125, fixture prefixReviewFE1792508879. URL8123 là bản chính. Demo account đã có từ seed của database review; tạo/gỡ quyền chỉ trong bản riêng. Mailer log được kiểm trước lời mời; không gửi mail thật. Cookies/state/HTML/serverlog và backup DB ở private runtime, không đóng gói.

## Lỗi và giới hạn được ghi lại

`flow-reports-queue-before-fix.json` giữ lỗi thực khi correction stale không giữ nội dung vì Referer vẫn `fresh=1`; đã sửa4edit controller consume+redirect và chạy lại flow qua. Không gọi quan sát thất bại này là PASS.

`flow-calendar-plan-initial-helper.json` là lỗi helper: sau lần stale đầu lại GET một form mới thay vì gửi lại body lỗi nên cố tình gửi dữ liệu cũ với version mới. Đã sửa helper dùng chính hiddenfields trong body lỗi, chạy trên eventfixture khác và xác nhận không ghi. Hai eventfixture đầu đều được giữ, không sửa DB trực tiếp để che lỗi.

Admin helper ban đầu bỏ hiddenstatus=active khi tự POST projectmember nên validator từ chối đúng; đã dùng form thật có hiddenfield, resume tiếp, không nới validator. Helper resume có một lần trùng biến s, sửa biến và tiếp tục. Không đưa lời mời/token đã tiêu thụ hoặc toàn laravel.log vào hồ sơ.

Browser đã quan sát FE desktop/mobile, đo settings390px: grid tràn360/343 trước sửa, sau không còn phần tử main tràn; Kanban5cột nằm trong local scroll, document không tràn viewport; xem form, chọn2bulk/check counter/revision/field theo action. Hộp native JSconfirm tại bước bulk cuối làm công cụ IAB kẹt; không ghi bước ấy là browser save thành công. Các thao tác server bulk đã có HTTP+SQL thành công. Bản dialog thử thay thế đã bỏ để giữ cơ chế gốc; tương tác client bị kẹt chưa được xác nhận đầy đủ. Ảnh quan sát được lưu riêng khi có thể, không dùng screenshot làm bằng chứng các thao tác chưa xảy ra.

Các driver .py là thủ tục HTTP/SQL thật, không thay app service hoặc giả requesthandler. Cần chạy setup bản riêng và chọn đúng source/state như file hướng dẫn; không chạy lên production. Chưa nghiệm thu SMTP/Redis production/provider AI/GitHub network, tải lớn, race đa process hoặc tất cả nhánh lỗi/accessibility. Lịch/timer dùng clock server thực; không thay thời gian SQL để ép flow.

# Minh chứng hoàn thiện F11–F14 và hồi quy

Ngày23/10/2026, bản cài riêng Windows/PHP/SQLite, HTTP127.0.0.1:8128. Không chạy reset/migrate trên DB chính. Không lớp test/mock; script điều khiển form session/CSRF thật, đọc SQL read-only, chạy PHP worker/scheduler thật. Dữ liệu demo review được giữ riêng, không đóng gói database/cookie/key.

13 flow COMPLETE; 165 điểm verified và 384 HTTP log trong acceptance-summary.json. Đây không phải test coverage. flow-review-fixture-repair là sửa dữ liệu fixture sau lỗi đã tìm; attempt-* là lần chưa đạt/debug, không tính nghiệm thu. setup.json có kết quả lint/Pint/migrate review/route/Blade. flow-operations.json chứng minh restore35bảng/privatefile và browser HTTP của bản restore.

flow-hours/team/watchers/templates là luồng chính. flow-edges kiểm permission/revoke/rollback/monthly/DST; flow-parallel chạy2PHPprocess thật; flow-delete-guard và flow-cancel-history kiểm bảo toàn lịch sử. regression-* chạy lại CRUD/workflow/calendar/Plan/report/queue bằng dữ liệu thực.

Ảnh desktop/mobile và browser-ui.json là quan sát hai trang thực; không là kiểm định accessibility toàn hệ thống. Báo cáo và hợp đồng FE ở ../../FULL_FUNCTIONAL_COMPLETION_2026-10-23.md và ../../FRONTEND_F11_F14_HANDOFF.md.

Walkthroughs được lưu để AI/người bảo trì có thể đọc và chạy lại trên bản cài review riêng. Bản portable dùng biến WM_REVIEW_ROOT, WM_REVIEW_URL, WM_EVIDENCE_DIR, WM_PHP; chỉ local/testing SQLite, loopback, không trỏ database chính. Đọc walkthroughs/README.md trước. Các file JSON hiện tại là kết quả lần chạy gốc, không thay thế khi replay.

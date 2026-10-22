# Minh chứng nghiệm thu 23/10/2026

Đọc [báo cáo cuối](../../COMPLETION_REPORT_2026-10-23.md) và summary.json trước. Các file flow-core, calendar-plan, reports-queue, admin, boundaries, github, quick-flow-http, pages đều có COMPLETE và không có assertion failed trong bản hoàn tất.

- release-install.json: cài source riêng, migration/seed lặp, login, không dùng database chính. Vendor copy từ máy đang cài, không gọi đó là tải Composer sạch hoặc máy thứ hai.
- flow-*.json: request HTTP và điều kiện SQL/HTML đã kiểm. HTTP status là sau redirect với form thông thường. Một số ca bảo vệ cố ý trả403/404/409/419/422.
- flow-findings-before.json: lỗi tái hiện trước sửa. Dòng task-list đầu không thấy task0 do phân trang, không dùng dòng đó làm bằng chứng tái hiện thành công; lỗi project/history có bằng chứng trực tiếp và tasklist/board được kiểm lại ởboundaries.
- flow-boundaries-attempt1/2.json: các lần dừng do giả định trong công cụ đọc kết quả; xem giải thích trong báo cáo, không xóa dấu vết hoặc tính vào số đạt.
- technical.json: lint, cache, Composerplatform,31bảng/env/key bảo toàn, FK và SQLreadback sau browser.
- composer-audit.json: không có advisory/abandoned theo composer.lock tại lúc chạy; không phải pentest.
- browser.json + PNG: kiểm trực tiếp390/1366px, preset90phút/Ctrl+Enter/Escape/focus, không tràn ngang và không consoleerror. mobile-before.png giữ hình lỗi header.

Không thêm TestCase/PHPUnit/mock. Dữ liệu tạo qua HTTP và đọc lại SQL; worker chạy Artisan thật. Không chờ20phút để chứng minh TTLconflict; không đánh giá load, mọi databaseengine hoặc providerAI thànhcông. Các giới hạn được giữ trong báo cáo.

Replay quick edit có helper đã có tại ../quick-edit/walkthrough.py, yêu cầu --review-root và --base-url; helper kiểm sessionCSRF thuộc DBchỉđịnh trước ghi. Chỉ chạy trên bản cài demo riêng, không trỏ vào database chính. Các walkthrough đợt toàn hệ thống lưu trong walkthroughs/ để đối chiếu thao tác, đọc hướng dẫn tại đó trước khi chạy.


Kiểm tra gói cuối: final-package-install.json và final-readiness.json. Nguồn runtime cuối trùng hash bản đã giải nén/cài; vendor cùng máy được tái sử dụng. USE_CASES.puml và ERD_CURRENT.mmd là nguồn sơ đồ; chưa chạy trình render PlantUML bên ngoài.

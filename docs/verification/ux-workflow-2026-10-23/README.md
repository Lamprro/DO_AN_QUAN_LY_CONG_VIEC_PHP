# Minh chứng cải thiện trải nghiệm — 23/10/2026

- `flow-experience.json`: lần đầy đủ cuối, 30 điểm xác nhận qua 59 request; session/cookie/CSRF thật, GET/POST/PATCH redirect, SQL readback. Mục COMPLETE xác nhận hoàn thành.
- `ux-browser.json`: thao tác browser1366×900/390×844, kiểm overflow/focus/menu/filter; SQL task mẫu sau sửa thành120phút và IN_PROGRESS. Screenshot: `ux-desktop-list.png`, `ux-project-guide.png`, `ux-mobile-journey.png`.
- `summary.json`: so sánh31bảng/.env chính, hash14file runtime đã đồng bộ, tổng bằng chứng. Không chứa nội dung DB thật hoặc APP_KEY.
- `technical.json`/`compilation.json`: PHP/Pint/JS/Blade/diff.
- `attempt-*.json`: dấu vết các lần kịch bản chưa hoàn tất do xử lý encoding/JSON. Không phải kết quả đạt cuối. Chi tiết trong báo cáo UX.
- `walkthrough.py`: script thủ tục HTTP/SQL, không dùng lớp test/mocks. Dùng bản Laravel **riêng**, seed demo và DB riêng, SESSION_DRIVER=database. Cần dữ liệu demo với demo1 Owner, demo5 Viewer và memberships đúng schema. Script tạo dữ liệu mới, không reset DB. Chỉ trỏ localhost của bản riêng; không chạy vào hệ thống đang dùng.

Ví dụ PowerShell trên một bản kiểm tra riêng đã chạy server:

```powershell
$env:WM_REVIEW_ROOT = 'C:\duong-dan-ban-kiem-tra-rieng'
$env:WM_REVIEW_URL = 'http://127.0.0.1:8126'
python -X utf8 docs/verification/ux-workflow-2026-10-23/walkthrough.py
```

Bản script trong thư mục này được xuất từ luồng đã chạy, thay đường dẫn cố định bằng biến môi trường, kiểm cú pháp. Không tuyên bố đã chạy lại toàn bộ bản xuất trên máy khác. Bản chạy nghiệm thu dùng SQLite cục bộ, không đo tải production hoặc AI/provider mạng.

Kiểm tra bổ sung cuối: `flow-final-smoke.json` gồm5điểm xác nhận/9request, không gian cá nhân và dashboard nhóm dùng đúng nhóm menu.

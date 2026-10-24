# Chạy lại flow thật trên bản cài riêng

Không phải TestCase/Mock. Không chạy trên production hoặc source/database đang dùng. Tạo bản cài Laravel riêng ngoài repo, .env local, SQLite riêng đặt trong bản cài đó, seed demo, mail log, AI/tích hợp tắt. Đặt APP_URL=http://127.0.0.1:8128 và khởi động server8128. Các kịch bản mail/restore hiện cố định loopback8128/8129; giữ hai cổng này trống trước chạy. Chỉ dùng dữ liệu review.

PowerShell:

```powershell
$env:WM_REVIEW_ROOT = 'C:\review\work-management'
$env:WM_REVIEW_URL = 'http://127.0.0.1:8128'
$env:WM_PHP = 'C:\xampp\php\php.exe'
# Optional WM_EVIDENCE_DIR must be outside the actual repo; default is review private storage.
python -X utf8 production_flow.py core
python -X utf8 production_flow.py calendar-plan
python -X utf8 production_flow.py reports-queue
python -X utf8 production_admin.py
python -X utf8 production_boundaries.py
python -X utf8 production_github.py
python -X utf8 production_auth_flow.py
python -X utf8 production_operations_flow.py
python -X utf8 production_security_edges.py
```

Thứ tự bắt buộc vì kịch bản sau dùng ID đã tạo bằng form của kịch bản trước. Operations tạo snapshot và bản restore tại thư mục cha của review; security-edges cần bản restore đó. Thư mục snapshot/restored phải chưa tồn tại. Operations chỉ chấm dứt cây tiến trình server restore8129 do chính nó tạo; bản dùng taskkill đã kiểm trên Windows. Để chạy trên Linux, thay phần quản lý process bằng cơ chế tương ứng rồi nghiệm thu riêng. Các token/mail/DB nằm private, không xuất vào evidence công khai.

Script có guard đường dẫn/env ban đầu nhưng không xác thực tất cả topology của host. Operator vẫn phải xác nhận đây là môi trường review. Không đổi guard để nhắm vào hệ thống thật. Đổi môi trường/harness phải kiểm lại kết quả; sự tồn tại file JSON không phải chứng cứ PASS nếu không có COMPLETE hoặc assertion bị lỗi.

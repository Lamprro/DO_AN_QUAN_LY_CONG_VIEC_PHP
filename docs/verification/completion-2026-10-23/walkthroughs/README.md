# Procedural walkthroughs — không TestCase

Đây là các thao tác HTTP/form/SQL đọc lại đã dùng. Chỉ chạy trên bản cài riêng đã setup-local --demo, mail log, server localhost. Set WM_REVIEW_ROOT (thư mục cóartisan/.env vớiSQLite nằm bêntrong), WM_REVIEW_URL (port riêng). Helper login kiểmCSRFsessionthuộcDBđó trước ghi. Mọi output vào storage/app/private/completion-replay của bản cài, không ghi đè minh chứng trongsource.

Thứ tự: python audit_flow.py core → python audit_flow.py calendar-plan → python audit_flow.py reports-queue → python audit_admin.py → python audit_findings.py → python audit_boundaries.py → python audit_github.py → python audit_smoke.py. audit_findings.py có nhãn BUG vì ghi nhận trước-sửa; khi chạy bảnđãsửa các cờdịchsai/phạmvirộng phải false. Nó cũng chuẩn bị history_task và literal_project cho boundaries.

Các dữ liệu lịch cố định2027 dùng trong bản nghiệm thu23/10/2026; khi chạy sau mốc này phải đổi sang khoảng tương lai phù hợp. Không xem kết quả cũ là kết quả vừachạy. audit_github.py tạm bậtINTEGRATIONS_ENABLED trong .env bảnreview, kýrequestlocal rồi khôi phục; không cần/chạmsecretGitHubthật. Không chạy trên máyproduction. Các script cóassert để phát hiện sai lệch, không mockservice hoặc tựviếtDBnghiệpvụ.

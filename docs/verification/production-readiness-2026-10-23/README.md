# Minh chứng rà soát trước production

179 điểm xác nhận và339 HTTP request đã ghi log, trong9 flow hoàn tất. Số điểm bao gồm kiểm tra exit code của command. Không cộng các attempt thất bại, browser hay request không được instrument vào số trên; không coi là coverage phần trăm.

| Nhật ký | Điểm xác nhận | HTTP ghi log | Trạng thái |
|---|---:|---:|---|
| [flow-account-security.json](flow-account-security.json) | 20 | 39 | Hoàn tất |
| [flow-admin.json](flow-admin.json) | 21 | 44 | Hoàn tất |
| [flow-boundaries.json](flow-boundaries.json) | 30 | 90 | Hoàn tất |
| [flow-calendar-plan.json](flow-calendar-plan.json) | 11 | 35 | Hoàn tất |
| [flow-core.json](flow-core.json) | 32 | 84 | Hoàn tất |
| [flow-github.json](flow-github.json) | 9 | 6 | Hoàn tất |
| [flow-operations.json](flow-operations.json) | 39 | 5 | Hoàn tất |
| [flow-reports-queue.json](flow-reports-queue.json) | 11 | 28 | Hoàn tất |
| [flow-security-edges.json](flow-security-edges.json) | 6 | 8 | Hoàn tất |

Môi trường: bản cài riêng từ gói nguồn, SQLite riêng, .env/key riêng, loopback8128; restore sang app/database riêng8129. Vendor đã cài được tái sử dụng trên cùng máy Windows; không phải tải dependency trên một VPS khác. Không thêm TestCase/Mock hay chạy PHPUnit.

- `install.json`: cài bản nguồn ban đầu; `technical.json`: PHP/Pint/JS/Composer audit/platform/cache/schedule.
- `preflight-production.json`: negative control thực — local không đạt cấu hình production, không phải báo cáo máy chủ thật.
- `production-browser.json`: keyboard/mobile390px, desktop1366px và console. Hình: [mobile](production-mobile-account.png), [desktop](production-desktop-verification.png).
- `flow-operations.json`: heartbeat âm/dương, Host giả, seed/setup guard, maintenance, backup manifest/FK/hash, phục hồi và tải file qua route có quyền.
- `flow-security-edges.json`: SMTP loopback lỗi, tài khoản disabled, DB outage500 và giải mã credential sau restore.
- `final-verification.json`: kiểm tra nguồn cuối, dữ liệu chính/.env không đổi, package/fresh-install khi hoàn tất.
- `attempt-*`: lần cài bootstrap chưa đúng, fixture thiếu, đọc mail log sai và auth rate limit bị dùng chung; không đưa vào số PASS cuối.

Thư local có token nằm trong storage/logs của **bản cài riêng**, không đưa lên Git. Không đưa SQLite, key hoặc backup vào tài liệu. Tài khoản demo/password là fixture công khai, không dùng production.

Các walkthrough Python được lưu ở `walkthroughs/`: request thật với cookie/CSRF, đọc SQL, lệnh worker thật; chỉ chạy theo hướng dẫn trong thư mục đó trên bản cài review riêng. Không dùng chúng để tạo dữ liệu trong production.

Final: đủ31 bảng chính và .env giữ nguyên; cài source cuối lặp2 lần giữ key/seed,8 CSS/JS có version trả200,311 runtime files của bản review trùng nguồn cuối. `source-security-audit.json` không phát hiện secret/database/env trong tập nguồn đã quét. Package manifest nằm trong ZIP và dist/source-manifest.json.

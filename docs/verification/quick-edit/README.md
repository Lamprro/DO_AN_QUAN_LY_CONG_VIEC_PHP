# Kiểm tra sửa nhanh, preset và đối chiếu

Ngày thực hiện: 20/10/2026. Giữ Laravel MVC/Blade. Không thêm TestCase, PHPUnit, mock hoặc seed lại DB chính. Các lệnh/request kiểm tra dùng bản cài riêng `wm-release-w4apgton/project`, cổng8125, database/key riêng. Dữ liệu phát sinh qua native form + session/CSRF, sau đó đọc SQL để xác nhận. Snapshot nguồn FE trước sửa và backup DB chính đặt riêng trong `storage/app/private/quick-edit-review/`, không đưa vào source package.

## Bằng chứng

- `flow-http.json`: request/status và kiểm tra SQL của chuỗi flow hoàn chỉnh; 409/422/403/404/419 là các phản hồi từ chối có chủ đích, không tính là lưu thành công.
- `flow-extra.json`: người đang được giao sau khi rời project, scope cá nhân, so title nguyên văn và SQL read-back sau browser.
- `technical.json`: syntax/Pint/Blade compiled lint/cache và bảo toàn DB/env/key chính.
- `quick-modal.png`, `quick-conflict.png`: ảnh từ trình duyệt thật; không phải mockup.

## Flow đã kiểm tra

- task persisted QuickEdit1792513236 original
- deadline reason rejection keeps draft, old revision and reopens modal
- quick PATCH persists metadata and preserves description/tree
- FormRequest required title reopens quick form and keeps blank input
- stale quick edit shows escaped comparisons without writes
- forged comparison revision rejected
- injected comparison field rejected
- missing field selection rejected
- comparison token cannot cross sessions
- comparison token cannot cross tasks
- third edit during comparison causes another conflict without writes
- explicit mixed choices save with one revision and history
- successful token replay rejected
- replaced token replay rejected
- full form stale uses comparison and preserves server
- full edit chosen fields persisted
- task persisted QuickEdit1792513236 viewer target
- Viewer forged PATCH denied with no writes
- CSRF enforced on quick PATCH
- GROUP quick form omits assignment and estimate
- ineligible assignment rejected, retained for correction
- Friday includes today; Saturday selects following Friday from calendar date data
- task persisted QuickEdit1792513236 zoned task
- server preset matches workspace date Pacific/Kiritimati
- actual saved UTC matches local preset Pacific/Kiritimati
- task persisted QuickEdit1792513236 zoned task
- server preset matches workspace date Pacific/Pago_Pago
- actual saved UTC matches local preset Pacific/Pago_Pago
- isolated foreign key integrity
- task persisted QuickEdit1792513236 current assignee
- ineligible existing assignee retained in rendered selected option
- unrelated quick save does not clear ineligible assignment
- task persisted QuickEdit1792513236 personal
- personal scoped task conflict keeps titles verbatim
- personal scoped resolution saves intended choices
- browser save and comparison read back from SQL
- foreign key integrity after extra flows

## Trình duyệt thực tế

Mở Quick Edit → 1h/+30p thành90 phút → preset ngày mai điền đúng chuỗi server → đóng/mở giữ title/estimate/deadline và trả focus → submit thiếu lý do, modal tự mở lại giữ bản nhập/revision → Escape trả focus → điền lý do và Ctrl+Enter lưu → trang chi tiết cập nhật → hai tab cùng sửa, tab cũ nhận trang đối chiếu → chọn mine và lưu → SQL xác nhận title,90 phút và deadline UTC. Ctrl+Enter trong form trạng thái không gửi. Dialog/ trang không tràn ngang ở viewport390px (dialog scrollWidth=clientWidth350, document390); đã reset viewport sau kiểm tra. Console không ghi lỗi JS trong các bước này.

## Giới hạn và những gì không giả lập

- Thứ Sáu/thứ Bảy được kiểm tra hàm quy tắc với dữ liệu ngày23/10 và24/10/2026; không đổi đồng hồ server, không gọi đó là flow HTTP xảy ra vào ngày Thứ Sáu. Hai timezone UTC+14/−11 được tạo workspace/project/task bằng HTTP thật, dùng preset hiện tại và đọc deadline UTC đã lưu.
- TTL20 phút và giới hạn5 bản đối chiếu có trong xử lý session; không chờ20 phút để chứng thực thời gian hết hạn trong lượt này. Không thay timestamp session/DB để tạo bằng chứng giả.
- Kiểm tra thuộc phạm vi sửa nhanh/preset/conflict và các handler bị ảnh hưởng; không tuyên bố mọi chức năng trên mọi trình duyệt đã hoàn thiện100%. Các báo cáo các đợt trước được giữ nguyên.
- Lượt đầu phát hiện lỗi Blade ở trang phản hồi422; đã sửa và chạy lại flow. Hai lỗi thao tác kịch bản (CSRF không đúng phiên, dùng task team không có project) và timezone alias không trong danh sách cho phép đã được sửa trong kịch bản; không nới policy/validator để làm chúng vượt qua. Nhật ký cuối ghi chuỗi chạy thành công.

## Chạy lại

`walkthrough.py` là kịch bản thao tác HTTP/SQL tuần tự, không phải lớp test. Dùng `--review-root` trỏ bản cài riêng đã seed demo accounts và `--base-url` cổng riêng. Script từ chối DB chính, dùng tài khoản demo cục bộ, tạo fixture mới qua form. Không chạy trên dữ liệu thật của người dùng. Ví dụ:

```powershell
python docs/verification/quick-edit/walkthrough.py --review-root C:/Temp/wm-review/project --base-url http://127.0.0.1:8125
```

Nhật ký replay mặc định ghi vào private storage của bản riêng, không ghi đè chứng cứ lần bàn giao này. Cần PHP trên PATH hoặc chỉnh đường dẫn PHP trong script theo máy. Không cấu hình/gọi AI/GitHub provider.

Kiểm tra vòng Tab cuối phát hiện focus đi khỏi dialog ở phần tử cuối; đã bổ sung xử lý Tab/Shift+Tab, kiểm tra lại hai chiều giữ focus trong modal và không có ID DOM trùng. Ảnh modal đã cập nhật theo mã cuối.

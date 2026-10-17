# Minh chứng phần 4 — flow thật

Ngày 17/10/2026, Laravel local 127.0.0.1:8123, SQLite work_management.sqlite, session database, private local filesystem. Không TestCase/PHPUnit/mocks, không reset/migrate:fresh. Script dùng CookieJar, CSRF/action HTML và SQLite read-only sau ghi. HTTP 200 có thể là form sau validation redirect: đọc errors và verified/DB read-back, không coi mọi 200 là thành công.

## Bằng chứng cuối

- http-flow.json / http-walkthrough.py: cây/chu trình, dependency/blocker, thực hiện/review/reject/approve, timestamps, stale form, GROUP aggregate, comment escape/ownership/soft-delete và HISTORY immutable qua HTTP.
- files-final-flow.json / files-final-walkthrough.py: strict MIME-extension, oversized, file thật TXT/PNG/OOXML container được tải xuống byte-for-byte + SHA256, scoped ID, soft-delete và private bytes; tiếp tục depth/reparent, fallback count, chống chuyển loại mất dữ liệu, audit retention và field injection.
- final-boundaries.json / final-boundaries-walkthrough.py: sau thời gian chờ FS tự nhiên, SQL started_at >= predecessor.completed_at+60 giây; foreign scope, thiếu CSRF, invalid transition, archive bottom-up/restore top-down và state machine Task personal.
- state-edges.json / state-edge-walkthrough.py: lý do cancel/reopen, timestamp reset, reopen DONE/re-review, duplicate/remove dependency, reopen blocker, thiếu reviewer.
- last-smoke.json / last-smoke-walkthrough.py: policy resolve chung server/Blade, tên file Unicode, bytes download và ẩn sửa/xóa sai điều kiện ở DONE.
- review-browser.png, tree-browser.png, browser-read-back.json: browser thực đăng nhập demo1, đi TODO → IN_PROGRESS → REVIEW → DONE trên P4 Comment File, thấy HISTORY và tree 3 cấp. Đây là hành động Owner workspace (ngoại lệ quản trị); flow Worker/Reviewer riêng đã kiểm tra bằng HTTP.
- routes.json, technical-checks.txt: inventory từ Laravel và kiểm tra kỹ thuật cuối, tách khỏi bằng chứng nghiệp vụ.

Canonical workspace: 01a14903-52da-70c8-9043-ffdc3072377a; project: 01a14903-560f-736d-bc79-b18f61ef8850. State/cookie và upload bytes tại storage/app/private, không công khai/commit. Tài khoản phase4.worker/reviewer/viewer.<stamp>@example.test là dữ liệu demo thật đã tạo trong flow.

## Lỗi thật phát hiện và cách xác minh lại

1. Helper HTTP chỉ lấy CSRF/_method, chưa gửi hidden status của blocker form: resolve bị validation, DB không đổi. Đã sửa helper, tiếp tục checkpoint, đọc status/resolved_by đúng. core-before-helper-fix.json giữ lần dừng; resume-core.py là lệnh đã dùng. Đây không phải bỏ qua validation của ứng dụng.
2. Mimes aggregate + extensions riêng lẻ cho TXT đổi tên PNG lọt qua. extended-flow.json giữ bằng chứng trước sửa. Đã thay bằng AttachmentFormat ghép MIME với extension và kiểm tra container Office. files-final-flow.json xác nhận PNG/PHP/Office giả/over-limit bị chặn không thêm row. File giả đã tạo trước sửa được gỡ qua endpoint thật, giữ audit.
3. Truy cập URL private storage phải 404 nhưng layout lỗi ngoài web middleware thiếu $errors, thành 500. files-before-404-view-fix.json giữ bằng chứng. Đã dùng isset($errors) trong layout, tái xác minh 404; bytes không hề được trả từ URL đó. resume-files.py lưu checkpoint tương ứng.
4. Một số script tiếp tục checkpoint thiếu biến page/lag_next, đã sửa biến và chạy tiếp từ bước chưa đạt; không báo ứng dụng bị lỗi từ NameError của helper. Các log cuối có marker hoàn thành và SQL/FK read-back. Script không phải suite có thể chạy lại vô điều kiện trên state đã đổi; đọc state và nội dung trước khi tái hiện.

Core có bước đặt tên “maximum depth” nhưng cha thử là WORK nên boundary thực tế là sai loại cha. Depth giới hạn thật được exercise riêng với GROUP ở cấp 3 trong files-final-flow.json: thêm cấp 4 bị từ chối và DB không ghi. Không suy rộng kết quả sai loại cha thành đã kiểm tra độ sâu.

## Những gì đã xác nhận

Worker không tự duyệt DONE; Reviewer có thể reject và approve; Viewer bị chặn ghi nhưng được tải file khi task.view hợp lệ. Dependency vòng/chéo project bị từ chối. FS chưa đủ lag chặn, sau đủ mốc cho bắt đầu. Blocker open chặn nộp duyệt. Reopen prerequisite có successor đã chạy bị chặn. Mở lại có reason xóa mốc thực hiện, lưu lịch sử cũ. Metadata REVIEW không sửa được. GROUP status tự tổng hợp, estimate không cộng trùng. Chuyển parent/order giữ scope, chống chu trình/depth. COMMENT được escape, tác giả sửa, soft-delete; giả type/source/actor không tạo HISTORY. Row HISTORY không sửa/xóa qua comment endpoint. Upload fake/oversize không có row mới; download đúng bytes, ID task khác 404, Viewer upload 403, deleted file 404. FK check cuối rỗng.

## Giới hạn thực tế

Chưa chạy MySQL/production/tải đồng thời hoặc sự cố đĩa/SQL. Chưa kiểm tra UI timer/lịch/tích hợp của phần sau. Office fixture chỉ kiểm tra container và bytes, không mở bằng Word/Excel hoặc parse nội dung. TXT/PNG/DOCX/XLSX đã exercise; PDF/JPEG/CSV có rule nhưng chưa exercise riêng. Không có antivirus/purge worker, file bytes soft-deleted giữ private. Chưa công bố performance cho cây/feed lớn. Không tuyên bố toàn bộ dự án đã hoàn thiện từ phần 4.

# Minh chứng hoàn thiện phần7–10

Tất cả thao tác nghiệp vụ mới kiểm tra qua HTTP thật trên Laravel đang chạy, CSRF/session thật, SQLite đọc lại chế độ read-only, worker database queue thật; có browser tạo phiên bản nháp và xem active plan. Không thêm TestCase/mocks, không chạy suite Laravel/PHPUnit. Pint --test chỉ là kiểm tra định dạng source.

## Nguồn bằng chứng

- http-flow.json: workspace/project review mới; manual plan draft chưa thay business; propose/approve/apply; task tree/dependency/calendar/history; apply2 lần không trùng; version mới supersede active cũ, giữ UUID; snapshot stale bị chặn; calendar conflict rollback; schema injection/cycle/CSRF/outsider; local schedule; WORK thực tế + start/stop timer HTTP + review/DONE/FS lag; 3 operation AI queued bằng job thật rồi failed đúng lý do khi AI tắt; không tạo task/lịch giả; config GitHub disabled.
- boundaries.json: rollback sau khi slot đầu tiên có thể đã insert, slot thứ2 trùng; lag FS sai bị chặn trước tạo draft; GROUP estimate0 bị từ chối; reject/reason; thành viên tạo draft nhưng không đề xuất/duyệt; Owner duyệt/apply; Member bắt đầu timer rồi bị suspend, không xem task, chỉ dừng clock mình được; phục hồi membership; integration.manage bị chặn; version cuối apply/idempotency thành công sau các guard mới.
- github-flow.json: máy chủ local phụ8124 bật flag **chỉ trong tiến trình**, secret ngẫu nhiên nhập qua route config rồi mã hóa DB; unsigned403, signed204, trùng delivery chỉ1 log/activity, repo khác422, task status không đổi, disable xong signed403. Không gọi GitHub network.
- final-readback.json: lưu report từ các timer HTTP thật, actual tổng0.11 phút (tổng2 phiên ngắn theo dữ liệu review, không dùng estimate), CSV/print200; plan cuối active; commit ID sai kiểu422 không ghi log; main integration vẫn tắt; FK hợp lệ, hàng đợi AI rỗng.
- release-install.json: source nén/giải nén thư mục ngắn riêng trong Temp, không .env/DB live; tái dùng vendor đang cài trên cùng máy (không tải dependencies từ Internet); helper chạy migration mới + seed2 lần, 5user/3workspace/3project/40task/20calendar, key mới được giữ khi chạy lần2; login HTTP và project/plan/timers/dashboard200 ở8125. Không coi là đã chạy trên máy vật lý thứ hai hoặc MySQL/MariaDB.
- technical-checks.txt, technical-summary.json, routes.json: kiểm tra PHP/Blade/Composer/route-config caches và FK, unique active/group/running clock. Các cache được clear lại sau kiểm.
- plan-browser.png: draftv3 được nhập và lưu qua trình duyệt thật; active-plan-browser.png: versionv4 đã apply và nút apply được ẩn. Browser được để ở trang active cuối.

## Dữ liệu để mở lại

Workspace: `01a14a19-d83e-72ff-8a43-3761cf21a289`; project: `01a14a19-dbb0-7349-a6c4-c5e424fc088c`. Đăng nhập demo1@example.test / Demo@123456 (tài khoản seed chỉ local). Browser draft `01a14a1e-b609-71aa-9435-dde312a0f92b`; active version cuối `01a14a20-7e40-73fb-8e2e-015549860037`. Report cuối `01a14a26-e5da-71e8-9e71-87d0066813aa`. Cookies/state nằm storage/app/private/final-review, được Git/ZIP bỏ qua; không đưa token/secret vào bằng chứng. DB review giữ lại, không reset dữ liệu cũ.

## Lỗi thực tế đã gặp và sửa

- before-blade-fix.json: chỉ thị Blade đứng sát nhau làm trang workspace500; đã tách ranh giới directive, compile và HTTP lại thành công.
- github-before-capture-fix.json: request signed gặp biến raw chưa đưa vào closure transaction; đã thêm capture, chạy lại signed/duplicate/repo/disable thành công.
- before-formatting-check.txt: Pint báo định dạng một số controller và cache tự sinh. Đã chuẩn hóa controller và chỉ quét source, loại cache generated khỏi lint format.
- before-windows-path-fix.json: copy vendor vào path quá dài Windows; chuyển bản cài kiểm tra sang thư mục Temp ngắn. Không thay runtime của dự án đang dùng.
- before-readback-helper-fix.json: helper SQL đọc nhầm cột metrics thay metrics_json; ứng dụng đã lưu report thành công, sửa helper và đọc lại actual/CSV/print thành công. Không sửa database để hợp với helper.

Các helper procedural kèm theo có ghi dữ liệu qua route; đọc kỹ, thay ROOT và chạy trên DB review riêng nếu muốn lặp lại. Không chạy lại một script chỉ để tạo thật nhiều fixture. Đây là log thao tác thực, không bộ test class.

## Giới hạn đã ghi nhận

AI bridge có code HTTP/config/timeout + schema + queue nhưng provider/key/model thật chưa được cấu hình, chưa có phản hồi AI thành công. Kiểm manual/local schedule chứng minh workflow Plan, không chứng minh chất lượng AI. GitHub chỉ signed payload local, chưa public webhook. Redis/SMTP ngoài, Windows hard-kill timeout, tải đồng thời nhiều web/worker, MySQL/MariaDB và máy vật lý thứ hai chưa xác minh. Không có video/nhật ký đóng góp cá nhân giả; nhóm tự điền và diễn tập theo DEMO_AND_CRITERIA.md.


Số liệu kiểm tra tự tổng hợp từ log cuối:

```json
{
  "flows": {
    "http-flow.json": {
      "observations": 87,
      "verified_true": 33,
      "verified_false": 0
    },
    "boundaries.json": {
      "observations": 48,
      "verified_true": 12,
      "verified_false": 0
    },
    "github-flow.json": {
      "observations": 18,
      "verified_true": 9,
      "verified_false": 0
    },
    "final-readback.json": {
      "observations": 15,
      "verified_true": 6,
      "verified_false": 0
    }
  },
  "technical": {
    "php_sources": 183,
    "compiled_views": 126,
    "routes": 128,
    "fk_errors": [],
    "duplicate_active_plan_groups": [],
    "duplicate_running_timers": [],
    "pending_ai_jobs": 0,
    "new_test_classes": 0,
    "test_suite_executed": false
  }
}
```


## Kiểm tra giao diện và history cuối

ui-final-readback.json bổ sung HTTP Task Blade hiển thị commit GitHub đã ký/linked, timer start/stop và link phiên bản plan; active plan không còn nút apply; tạm đổi thành viên thành Viewer để xác nhận không có nút tạo/AI/edit/schedule rồi phục hồi Member. Có 7 điều kiện xác minh đều true. Sau các trang runtime, cache có 137 PHP view files (bao gồm fragments sinh khi request), tất cả qua php -l; con số126 ở technical-summary là sau view:cache trước các request runtime bổ sung. Không coi cache count là số trang tính năng.

Gói source tạo bằng scripts/package-source.py ở dist/work-management-mvc-source.zip, kèm source-manifest.json kiểm hash từng file. Không có .env/key/DB live/vendor/private storage/cookies. Dist được ignore khỏi Git; dùng file trực tiếp trên máy để chuyển giao. Cài lại theo DEPLOYMENT.md. Mã ứng dụng chạy local tại8123, các máy chủ review8124/8125 đã dừng.

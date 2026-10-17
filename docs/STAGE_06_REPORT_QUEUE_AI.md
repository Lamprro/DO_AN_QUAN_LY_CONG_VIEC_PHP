# Phần 6 — Dashboard/report, queue và nền AI

17/10/2026. Phần 1–5 đã được chủ dự án duyệt. Phần 6 có backend/Blade và minh chứng local, **dừng chờ duyệt**, không tự triển khai phần 7. Không thêm migration, test class/mocks, không reset DB/APP_KEY/.env, không gọi nhà cung cấp AI hay cài SDK mới.

## 1. Phạm vi và đường đi

- Dashboard với lọc kỳ/project/người được giao WORK hiện tại/include_archived, trạng thái, quá hạn, blocker, tiến độ và workload theo người.
- Snapshot số liệu tính bởi backend, bất biến, danh sách có phân trang; bản in Blade và CSV stream có kiểm tra quyền. Không sinh dữ liệu báo cáo từ AI.
- Nền AI **chỉ operation=report**, nhận xét bản chụp số liệu được phép xem: lưu AiRun + database job, worker ghi AiCall/trạng thái, chống gửi trùng, hủy/thử lại có giới hạn, lệnh phục hồi run bỏ dở.
- Provider mặc định UnconfiguredAiProvider; AI_ENABLED mặc định false. Chưa có adapter mạng/key/provider đã chọn. Không có success giả. Planning/replanning/assistance, Plan version/schema/apply thuộc phần 7–8, không có endpoint trong phần này.

## 2. Dashboard và quy tắc số liệu

GET `/workspaces/{workspace}/dashboard` → `ReportRequest::prepareForValidation()/rules()` → `DashboardController::index()` → `ReportService::metrics()` → Blade reports/dashboard + _metrics.

GET thiếu kỳ dùng tháng hiện tại trong users.timezone. Ngày from/to dạng Y-m-d, to loại trừ, kỳ tăng và tối đa một năm. POST tạo report/AI bắt buộc gửi kỳ. `ReportService::filters()` kiểm tra project đúng workspace, quyền và subject là thành viên active/user active. Personal workspace luôn ép subject=actor.

`ReportAccess::scope()` yêu cầu workspace.view và report.view. Nếu chọn project còn cần project.view, task.view, report.view và permission tương ứng trên role project riêng. Dashboard tổng hợp chỉ lấy project actor được report.view + task.view; task null-project chỉ trong workspace personal của Owner. Không dùng tên role để thay kiểm tra quyền.

`ReportService::metrics()` dùng transaction đọc nhất quán, lấy WORK, mặc định bỏ archived; tối đa10.000 WORK và20.000 calendar/entry ứng viên. Quá giới hạn báo thu hẹp phạm vi/kỳ, không âm thầm cắt. GROUP/MILESTONE không cộng vào WORK/estimate/actual. Filter subject dùng current assignee, không tái dựng lịch sử phân công. Calendar/time entry còn lọc owner theo subject khi chọn người.

Các nhóm chỉ số:

| Nhóm | Công thức/nguồn |
|---|---|
| inventory hiện tại | Trạng thái WORK lúc tạo/xem, không lọc created_at theo kỳ và không tái dựng trạng thái quá khứ. CANCELLED có bộ đếm riêng. |
| overdue | deadline_at < generated_at và status khác DONE/CANCELLED, theo scope/include_archived. |
| blockers | Số blocker open trên WORK không cancelled và số task distinct có blocker. |
| estimate | Tổng estimate_minutes của WORK không cancelled, null có bộ đếm unknown; không cộng nhóm/cha. |
| progress | Tái dùng TaskTreeService::progress(): bỏ CANCELLED, toàn bộ estimate>0 thì weighted; nếu thiếu/0 dùng số WORK. |
| completed_work trong kỳ | status hiện tại DONE và completed_at thuộc [startUTC,endUTC). Mở lại task làm thay đổi dashboard mới, không sửa snapshot cũ. |
| planned_minutes trong kỳ | TASK_BLOCK không cancelled của WORK không cancelled, giao với kỳ, và CalendarEventPolicy::view cho phép đọc đầy đủ. Không lấy lịch private của người khác ngay cả Owner workspace. |
| actual_minutes trong kỳ | Chỉ TimeEntry đã ended, giao với kỳ, từ WORK theo scope; giữ phiên thực tế đã ghi cả khi task CANCELLED. Không suy từ status/estimate/lịch. Không có phiên kết thúc trong kỳ trả null và nhãn “chưa có dữ liệu”. |
| running_entries_excluded | Entry chưa ended, bắt đầu trước cuối kỳ; đếm riêng, không tự đoán số phút actual. |

`minutes()` gộp các khoảng chồng/liền nhau **theo mỗi người**, sau khi cắt giao với kỳ, làm tròn0.01 phút; tổng là cộng các nhóm người. Workload tách current assignee với chủ lịch/phiên: người đã có thời lượng nhưng hiện không được giao task có thể có dòng tasks=0. Không tính số commit thành hiệu suất.

Metrics JSON schema_version1 có generated_at UTC, timezone/kỳ from/to và start/end UTC, filters, inventory, period_metrics, workload, definitions, work_schedule. sources giữ UUID task/calendar/time entry/blocker để đối chiếu SQL. `work_schedule` lấy cùng config BE đã dùng ở phần 5; không thêm giờ mặc định vào user_settings.

## 3. Snapshot, quyền xem, bản in và CSV

POST reports → ReportRequest → `ReportController::store()` → `ReportService::snapshot()` → metrics với permission report.create → tạo ReportSnapshot. Scope project nếu có project; personal nếu subject; còn lại team. Server tự gán workspace/project/subject/generated_by/kỳ/metrics; không nhận metrics_json/ai_commentary/scope từ client.

Snapshot không có route edit/delete. IntegrityObserver chặn Eloquent update/delete ReportSnapshot. Lưu bản mới thay vì sửa bản cũ. Việc user đổi task/estimate/blocker hoặc có entry mới không đổi metrics_json đã lưu; CSV và bản in lấy JSON đã lưu, không query lại để tính số liệu.

Snapshot mặc định chỉ người tạo được xem; **chưa có chia sẻ report giữa người dùng**. `ReportSnapshotPolicy::view()` còn gọi `ReportAccess::manifest()` kiểm tra quyền hiện tại trên workspace, project đã chọn và mọi project/calendar scope có đóng góp số liệu. Mất quyền project/calendar làm bản cũ bị ẩn/403, không dùng snapshot để lách quyền. Manifest không cần event còn tồn tại: số liệu lịch sử vẫn đọc được nếu quyền scope còn đúng. Bản seed/legacy thiếu schema/access manifest không được coi là report workflow mới.

GET index: own snapshots schema1, lọc project/ngày tạo, kiểm tra policy trước phân trang20; tối đa1.000 ứng viên và báo lọc thêm nếu vượt. Không lộ tiêu đề/ID của bản actor không còn quyền xem. `bound()` kiểm tra workspace_id và policy trên show/print/export; không dựa vào relation suy diễn từ tên parameter.

`ReportController::export()` stream CSV UTF-8 BOM, file report-{UUID}.csv, private/no-store + nosniff. fputcsv escape chuẩn; chuỗi có tiền tố =,+,-,@,tab,CR được thêm apostrophe để không bị thực thi công thức. Không ghi file vào public hay lưu storage_key giả. Bản in là HTML + CSS print, dùng Ctrl+P; không tuyên bố đã có PDF engine.

## 4. Gửi yêu cầu AI và queue nguyên tử

GET ai-runs/create → chọn cùng filters Dashboard + prompt + idempotency_token UUID server tạo. POST → `AiRunRequest` (chỉ field được phép, không nhận operation/status/context/requested_by/provider/model) → `AiRunService::request()`.

Trong transaction:

1. Khóa Workspace, kiểm tra report.create.
2. idempotency_key=SHA256(actor+workspace+form-token). Context ghi request_digest từ nội dung, kỳ, scope và timezone. Cùng key/nội dung trả run cũ, không thêm job; cùng key khác nội dung báo lỗi. Không dùng token form làm API credential.
3. Tính metrics qua cùng ReportService, lưu context schema1 + metrics + work_schedule + mode report_commentary_only. Không đưa .env, credential/cookie, file, comment, lịch private không được xem hoặc raw activity vào context. Prompt tự người dùng nhập được lưu như nội dung yêu cầu, không chạy như code.
4. Tạo AiRun queued, operation report, trigger manual, requested_by actor. `enqueue()` dùng Queue::connection(database), queue work-management-ai. Job chỉ chứa runId, trước commit; queue DB phải cùng connection với domain DB, khác connection thì rollback/báo lỗi. Không fallback sync/deferred. Run và job được insert trong cùng transaction database; web request không thực thi provider.

Không giới thiệu producer fake cho mục đích test. Form và route này là nền cho nhận xét báo cáo thật ở phần sau, với lỗi rõ nếu chưa cấu hình.

## 5. Worker, trạng thái và adapter

`ProcessAiRun::handle()` → `AiRunService::begin()` khóa run queued, kiểm tra lại quyền requester trên context; nếu mất quyền thì failed trước provider, không tạo lượt gọi giả. Nếu đủ quyền: giới hạn tối đa3 attempts toàn run, chuyển running, tạo AiCall report_commentary + attempt_no, provider/model config, started_at và fingerprint context/job UUID (không có secret/raw HTTP).

Sau commit transaction ngắn mới chạy adapter ngoài transaction. Hiện AI_ENABLED=false gây AiProviderUnavailable: job ghi failed rõ “AI đang tắt; chưa gọi nhà cung cấp…”, response/tokens null, finished_at thật. Đây là lỗi cấu hình đã được xử lý: queue job DONE/ack là bình thường, **không có nghĩa AiRun succeeded** và không thêm failed_jobs cho lỗi domain này.

`AiProvider` contract và `AiProviderResult` DTO đã có; binding mặc định UnconfiguredAiProvider chỉ báo chưa cấu hình. Bật AI_ENABLED một mình vẫn không tạo provider thật. Sau khi chọn provider/key ở phần sau, backend cần adapter mạng enforce ai.timeout_seconds30, public errors không chứa credential/raw response nhạy cảm, trả text≤20.000 ký tự và token counts nullable không âm. Không thay adapter bằng fake thành công.

`finish()` khóa run/call, kiểm tra call running/latest attempt, run chưa cancelled và quyền còn đúng trước khi lưu. Success chỉ lưu response text của adapter, không ghi vào metrics snapshot và không sửa task/calendar/plan. Kết quả đến muộn của run đã hủy/đã recover không ghi đè lần mới.

Lỗi cấu hình/schema là terminal failed, không retry tự động. Lỗi runtime khác: lưu thông báo công khai cố định, xóa raw exception khỏi queue error, đưa queued nếu còn lượt và để worker retry backoff5/20 giây. Job tries3, timeout45s, failOnTimeout true; global attempt cap vẫn áp dụng qua retry người dùng. Đường transient/network-success chưa xác minh vì không có provider thật, không được báo đã test.

## 6. Hủy, thử lại, run bỏ dở

POST cancel/retry → bound + AiRunPolicy view/manage → `AiRunService::action()` khóa run, expected_status chống form cũ. Chỉ requester và report.create hiện tại được manage.

- Cancel chỉ queued/running → cancelled + finished_at. Job queued còn trong jobs sẽ no-op khi worker nhận; nếu adapter đã chạy không cam kết hủy HTTP ngay, nhưng kết quả không được persist thành success.
- Retry chỉ failed còn <3 AiCall → queued + job cùng transaction. Không reset context/metrics/prompt; muốn lấy số liệu mới thì form mới. Hết3 lượt thì mở yêu cầu mới. Duplicate retry/stale form không enqueue thêm.
- failed() worker dùng job UUID đối chiếu latest call trước khi đánh run failed; không để job cũ ghi đè run đang được xử lý bởi job mới.
- `php artisan ai:recover-stale`: chỉ running quá180s, kiểm tra lại cutoff dưới lock, đóng call running và run failed; không fake success và không sửa business data. Có thể retry nếu còn lượt. Chưa bật scheduler tự động, chưa kiểm chứng kill/timeout process thật.

Windows PHP hiện không có pcntl_alarm, nên không coi worker timeout45s là hard timeout đã được chứng minh trên máy này. Adapter tương lai phải có network timeout riêng; production cần giám sát worker. Lệnh recovery mới xác minh trường hợp0 stale, không giả đồng hồ/SQL để tạo stale case.

Lệnh worker đã chạy thật: `php artisan queue:work database --queue=work-management-ai --stop-when-empty --sleep=1 --tries=3 --timeout=45`. Helper scripts/work-ai.ps1 hỗ trợ chạy lâu hoặc -Drain. Chỉ consume queue riêng, không flush/default queue của chat khác. Không cần Redis/SDK/key cho flow queue lỗi cấu hình hiện tại.

## 7. Xác minh và điểm dừng

Xem verification/stage06/README.md: HTTP/session/CSRF → controller/service/SQLite → worker → HTML, browser tạo snapshot và request queued→failed, metadata thay đổi không làm snapshot đổi, private/calendar quyền thu hồi, CSV, clip kỳ và attempt caps.

Để kiểm tra nhánh actual, dùng TimeEntry model có sẵn qua Artisan bootstrap/CLI tạo một phiên thật3 giây rồi ended_at=now(), không giả ngày/đồng hồ. Chưa có timer UI nên ghi rõ đây là chuẩn bị nguồn qua model, không tuyên bố timer browser end-to-end. Snapshot thực qua HTTP đọc0.05 phút; phiên đang chạy không cộng actual; TaskWorkflow chặn cancel khi timer chạy; hủy task sau đó không làm mất actual. Task được restore, entry đã kết thúc và giữ làm minh chứng.

Chưa có model provider thật, successful AI/network/transient retry, hard timeout/crash recovery, MySQL/production/load verification hoặc Plan apply. Không triển khai phần 7. Dừng chờ chủ dự án duyệt phần 6.

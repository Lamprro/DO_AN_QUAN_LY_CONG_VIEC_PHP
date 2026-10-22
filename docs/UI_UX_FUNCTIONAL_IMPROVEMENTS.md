**20/10/2026 — Đã tích hợp và rà soát FE mới.** Sửa các lỗi nguồn/phiên bản/form và responsive; thêm tìm kiếm theo quyền, Kanban Blade. Xem [đánh giá và flow](POST_FE_REVIEW.md), [bàn giao FE](FRONTEND_HANDOFF.md), [minh chứng mới](verification/post-fe/README.md). Các mô tả cũ là lịch sử, không là cam kết100%production.

**18/10/2026 — Đợt E/F04–F10 đã triển khai:** presets, bộ lọc riêng, bulk nguyên tử, nhắc in-app qua queue, theo dõi hạn, quan sát vận hành có quyền và sửa giờ có lịch sử. Xem [flow E](PHASE_E_UTILITIES.md), [bàn giao FE](FRONTEND_HANDOFF.md), [minh chứng thật](verification/phase-e/README.md). P2 và cấu hình dịch vụ ngoài vẫn theo backlog; chưa cài scheduler/worker thành service production.

# Kế hoạch cải thiện UI/UX và chức năng

**18/10/2026 — Đợt D/P1 quan sát đã triển khai.** Xem [P1_OBSERVATION.md](P1_OBSERVATION.md) và bàn giao FE mới nhất. P0 giữ nguyên; đợt E/F04–F10 đã triển khai ở cập nhật phía trên; P2 còn là backlog. Các mô tả “chưa có/cần bổ sung” của bản kế hoạch gốc là lịch sử trước nâng cấp.

> Cập nhật 17/10/2026: vòng P0 đã triển khai, xem [P0_IMPLEMENTATION.md](P0_IMPLEMENTATION.md) và [bàn giao FE](FRONTEND_HANDOFF.md). Bảng “hiện trạng” và các câu “chưa có/cần bổ sung” trong bản kế hoạch gốc bên dưới mô tả **trước vòng P0**. F01/F02/F03 nay có mã và minh chứng; P1/P2/KEY còn là backlog. Preview dùng đường apply thật trong transaction rồi rollback, không lưu thay đổi nghiệp vụ (không phải hàm chỉ SELECT).

Ngày lập: 17/10/2026. Áp dụng cho source Laravel MVC hiện tại tại `D:\HeThong_PhanTichTaiChinh\DO_AN_QUAN_LY_CONG_VIEC_PHP`. Đây là tài liệu đề xuất và giao việc tiếp theo, **không phải danh sách tính năng đã triển khai thêm**. Các nhận xét hiện trạng dựa trên Blade, CSS, controller/service và tài liệu bàn giao trong repo; không coi đây là kết quả một đợt nghiên cứu người dùng hoặc kiểm tra tải thực tế.

## 1. Mục tiêu và giới hạn

Ưu tiên giúp người dùng hiểu đang ở đâu, cần làm gì tiếp, dữ liệu đã lưu hay chưa và vì sao một thao tác bị chặn. Giao diện phải phục vụ trọn quy trình: nhận việc → thực hiện → ghi giờ → nộp duyệt → hoàn thành → xem báo cáo; quản lý lập kế hoạch → so sánh → duyệt → áp dụng.

- Giữ Laravel MVC/Blade, session, CSRF, form và route có thật. Chưa cần đổi sang SPA hoặc dựng thêm REST API để làm UI đẹp hơn.
- Backend quyết định quyền, trạng thái, phân công, xung đột lịch, số liệu và áp dụng kế hoạch. JavaScript chỉ hỗ trợ tương tác và phản hồi sớm.
- Không hiển thị thành công trước khi backend xác nhận; không tạo dữ liệu AI mẫu để thay kết quả provider.
- Các đề xuất thay quy tắc nghiệp vụ, quyền, cấu trúc dữ liệu phải được thống nhất trước khi thực hiện; tài liệu này chưa cho phép tự mở rộng quyền đặt lịch của đồng đội hoặc sửa lịch sử đã áp dụng.
- Không thêm lớp test thay cho xác minh thực tế. Mỗi phần triển khai sau này phải kiểm bằng thao tác thật, HTTP/Blade, dữ liệu đọc lại và worker khi liên quan.

### Ký hiệu dùng trong tài liệu

| Ký hiệu | Ý nghĩa | Cách triển khai |
|---|---|---|
| UI | Backend đã có phần xử lý chính | Làm presentation/tương tác, giữ contract hiện tại |
| UI+BE | Cần thêm controller/request/service/query hoặc hợp đồng dữ liệu | Ghi rõ dữ liệu server cấp và quyền trước khi làm FE |
| DB | Có thể cần migration hoặc thay ERD | Chốt mô hình, migration bổ sung, kế hoạch bảo toàn dữ liệu |
| KEY | Cần cấu hình dịch vụ thực | Chỉ nghiệm thu sau khi kết nối thật; trước đó hiển thị chưa cấu hình |
| P0 | Cần xử lý trước vòng demo/nghiệm thu UI tiếp theo | Hoàn thiện luồng cốt lõi và tránh hiểu sai dữ liệu |
| P1 | Hoàn thiện sau khi P0 chạy ổn định | Giảm thao tác và tăng khả năng sử dụng hằng ngày |
| P2 | Mở rộng có chọn lọc | Làm khi có nhu cầu rõ và thời gian phù hợp |

## 2. Hiện trạng cần cải thiện

| Hiện trạng đã thấy trong source | Hệ quả với người dùng | Hướng xử lý | Ưu tiên |
|---|---|---|---|
| Layout có header/nav chung; chưa có bộ khung điều hướng workspace/project xuyên các trang | Khó biết phạm vi hiện tại, phải quay lại tổng quan để chuyển module | App shell với chọn workspace, sidebar theo quyền, breadcrumb project | P0 · UI+BE |
| CSS chủ yếu là một file shell với `.card`, bảng và media query cơ bản | Các trang dài, mức độ nổi bật của hành động chưa nhất quán | Component và token chung, bố cục theo từng loại màn hình | P0 · UI |
| Trạng thái/loại/ưu tiên còn xuất hiện bằng giá trị enum như TODO, WORK, medium | Người dùng phải hiểu mã kỹ thuật | Nhãn tiếng Việt dùng chung, màu luôn kèm chữ; giữ enum khi gửi form | P0 · UI |
| Plan create là textarea JSON; thành viên hiển thị cả UUID | Người dùng phổ thông khó soạn kế hoạch hoặc sửa tham chiếu | Trình soạn bảng/cây, select người, serialize payload đúng schema | P0 · UI+BE |
| Plan compare hiển thị JSON phiên bản này và trước đó | Khó nhận ra đổi estimate, người làm, dependency hoặc slot nào | Bảng diff theo ref/task_id, nhóm thay đổi và tổng ảnh hưởng | P0 · UI+BE |
| Lỗi có summary ở layout; các màn hình cần được rà soát lỗi cạnh trường | Phải tìm lại trường sai, đặc biệt với form nhiều dòng | Error summary có link, lỗi inline, giữ old input | P0 · UI |
| Bảng được đặt min-width640px để cuộn ngang | Tránh vỡ bảng nhưng thao tác điện thoại còn tốn công | Card/list mobile hoặc ưu tiên cột, giữ bản bảng đầy đủ trên desktop | P0 · UI |
| Calendar/Timeline đã có dữ liệu, song presentation hiện khá đơn giản | Khó nhìn ngày, khoảng thời gian và chồng lấn | Lưới lịch, trục giờ, legend, chi tiết sự kiện; không suy ngày từ estimate | P1 · UI |
| Report có số liệu và định nghĩa nhưng chủ yếu hiển thị văn bản/bảng | Khó đọc nhanh tình hình và phân biệt hiện trạng với kỳ báo cáo | KPI, chart từ aggregates server, timestamp và giải thích ngắn | P1 · UI |
| Timer có trang riêng, thao tác start tại task | Dễ quên đang chạy timer khi chuyển trang | Thanh timer toàn cục, link đến việc khi còn quyền, nút dừng luôn dễ tìm | P0 · UI+BE |
| Tài liệu FE tích lũy theo từng giai đoạn có các đoạn mô tả trạng thái cũ | AI tiếp nhận có thể hiểu nhầm module đã có thành chưa có | Chỉ dẫn hiện trạng ở đầu file, đọc phần bàn giao cuối và DELIVERY_STATUS trước | P0 · Tài liệu |

## 3. Kiến trúc thông tin và điều hướng

### 3.1. Khung trang chung

**Desktop:** header chứa chọn workspace, tên project hiện tại khi có, thông báo, timer và menu tài khoản. Sidebar gồm các nhóm có quyền truy cập. Vùng nội dung có breadcrumb, tiêu đề, mô tả ngắn, một hành động chính và khu vực dữ liệu.

**Mobile:** header gọn, menu mở theo nút, tên workspace/project có thể xuống dòng; nội dung chính không bị header chiếm phần lớn màn hình. Drawer menu đóng được bằng Escape và trả focus về nút mở. Không giấu nút dừng timer sau nhiều tầng menu.

Đề xuất menu:

| Phạm vi | Mục |
|---|---|
| Tài khoản | Workspace, giờ thực tế của tôi, thông báo, hồ sơ, cấu hình |
| Workspace team | Tổng quan, projects, thành viên, vai trò, dashboard/báo cáo theo quyền |
| Project | Tổng quan, công việc dạng danh sách/cây, lịch/Timeline, kế hoạch, ảnh hưởng, báo cáo, thành viên, GitHub logs theo quyền |
| Workspace personal | Công việc cá nhân, lịch, kế hoạch, báo cáo |

Workspace team không có nghĩa người dùng được xem mọi project. Không tạo link plan/task ở scope null cho team; chuyển người dùng tới danh sách project hợp lệ. Workspace personal mới dùng task/plan scope null.

### 3.2. Dữ liệu cần backend cấp cho app shell

Tạo view data/composer hoặc đối tượng trình bày dùng chung, có ít nhất:

- Workspace/project hiện tại, danh sách workspace được chuyển, URL breadcrumb.
- Menu đã lọc quyền, URL thật, trạng thái active; không suy quyền từ tên Owner/Manager.
- Unread notification count hiện có; timer đang chạy của chính người dùng nếu bổ sung.
- Các cờ `canCreate`, `canManageMembers`, `canViewPlans`… được tính tại backend cho scope hiện tại.

Không cho FE query DB trong Blade để dựng menu. Khi chuẩn hóa, chuyển các lời gọi service kiểm quyền rải rác trong view sang dữ liệu trình bày do controller/composer cấp; backend vẫn kiểm lại tại endpoint.

**Nghiệm thu:** mở thẳng URL task hoặc plan từ ngoài vẫn thấy đúng breadcrumb; chuyển workspace không giữ project_id cũ; người chỉ có quyền project A không thấy link/danh sách của B; bấm Back giữ bộ lọc hợp lý.

## 4. Hệ thống thiết kế và quy tắc tương tác

### 4.1. Nền tảng giao diện

Đề xuất ban đầu: nội dung thường16px, phụ14px, tiêu đề trang24–30px; khoảng cách theo bội4/8px; form nội dung dài rộng khoảng640–760px; bảng sử dụng vùng nội dung rộng hơn. Đây là điểm xuất phát để thiết kế, không phải yêu cầu đã được kiểm chứng với người dùng.

- Tạo token màu nền, surface, chữ, border, focus, primary, success, warning, danger; đủ biến cho light/dark/system.
- Tránh định nghĩa dark mode chỉ bằng đổi nền/chữ tổng thể; phải kiểm input/select, badge, notice, chart, disabled, modal và table header.
- Một nút chính trên mỗi khu vực quyết định. Hành động hủy/lưu trữ đặt trong menu phụ; không dùng màu nguy hiểm cho thao tác bình thường.
- Nút chỉ icon phải có tên truy cập; nội dung dài như UUID, mã task, URL và commit không đẩy tràn viewport.
- Dùng định dạng ngày/giờ thống nhất và có nhãn múi giờ ở lịch/báo cáo. Phân biệt múi giờ workspace với múi giờ hiển thị tài khoản.

### 4.2. Component nên chuẩn hóa

| Nhóm | Component đề xuất | Hành vi bắt buộc |
|---|---|---|
| Khung | app-shell, sidebar, breadcrumb, page-header | Active navigation, mobile drawer, scope rõ |
| Form | form-field, input, textarea, select, checkbox, date/time-field | Label/ID duy nhất, hint, old input, lỗi cạnh trường |
| Dữ liệu | data-table, mobile-item-card, filter-bar, pagination | Giữ query string, empty state, sort whitelist |
| Trạng thái | status-badge, priority-badge, alert, toast | Màu kèm chữ; thông báo quan trọng không tự biến mất quá sớm |
| Quyết định | confirm-dialog, action-menu, reason-dialog | Focus/Escape, thông tin đối tượng, không POST chỉ vì mở modal |
| Công việc | task-row, task-tree-node, assignee-picker, activity-item | Phân biệt GROUP/WORK/MILESTONE và quyền theo task |
| Lịch/plan | calendar-event, timeline-row, plan-diff-row | Hiển thị khoảng thời gian, nguồn, thay đổi chưa áp dụng |

### 4.3. Form và lỗi

1. Label nói theo nghiệp vụ, không dùng tên cột DB làm nội dung chính.
2. Submit lỗi: summary ở đầu form, focus summary/trường lỗi đầu, link tới trường; giữ mọi dữ liệu hợp lệ đã nhập.
3. Đánh dấu bắt buộc rõ bằng chữ hoặc chú thích chung; không chỉ dựa vào dấu `*`/màu.
4. JavaScript có thể kiểm sớm nhưng lỗi server là kết quả cuối. Có thể submit khi JS tắt.
5. Chỉ khóa nút submit khi form hợp lệ và bắt đầu gửi. Không disable toàn bộ input/CSRF khiến trường bị loại khỏi request. Mở lại nút khi lỗi hoặc quay lại bằng browser.
6. Không tự retry POST khi mạng lỗi/419. Task CRUD chưa có idempotency tổng quát; chỉ áp dụng lại theo contract nơi backend đã hỗ trợ như AI request/plan apply.
7. Form dài cần cảnh báo chưa lưu trước khi rời trang. Autosave phía server là chức năng mới; không tự lưu dữ liệu công việc nhạy cảm vào localStorage.

### 4.4. Ngôn ngữ và thuật ngữ

| Giá trị gửi backend | Nhãn người dùng đề xuất |
|---|---|
| TODO | Chưa bắt đầu |
| IN_PROGRESS | Đang thực hiện |
| REVIEW | Chờ duyệt |
| DONE | Hoàn thành |
| CANCELLED | Đã hủy |
| GROUP / WORK / MILESTONE | Nhóm việc / Công việc / Mốc |
| draft / proposed / approved / rejected / superseded | Bản nháp / Chờ duyệt / Đã duyệt / Bị từ chối / Đã được thay thế |
| queued / running / failed / cancelled / succeeded | Đang chờ / Đang xử lý / Thất bại / Đã hủy / Thành công |

Plan `approved` chưa đồng nghĩa đã áp dụng: hiển thị thêm `applied_at` và `is_active`. GROUP có trạng thái tổng hợp, không đưa menu chuyển trạng thái trực tiếp giống WORK.

## 5. Yêu cầu cải thiện theo màn hình

### 5.1. Đăng nhập, đăng ký, hồ sơ — P0/P1 · UI

- Form auth gọn, thông báo lỗi dễ đọc, nút hiện/ẩn mật khẩu không làm mất focus hoặc đổi giá trị.
- Cho trình quản lý mật khẩu hoạt động đúng với autocomplete phù hợp; không chặn paste.
- Đăng ký giải thích workspace cá nhân sẽ được tạo. Timezone có tìm kiếm, giá trị lưu vẫn là định danh được server cho phép.
- Hồ sơ chia “Thông tin cá nhân” và “Đổi mật khẩu” thành hai form rõ ràng; giữ yêu cầu current_password khi backend yêu cầu.
- Múi giờ: có ví dụ giờ hiện tại để người dùng kiểm tra lựa chọn; không âm thầm đổi dữ liệu UTC đã lưu.
- Quên mật khẩu/email verification là **UI+BE+KEY**, chưa được coi là có chỉ vì có trang login. Chỉ thêm nút khi có route, token, giới hạn request và kênh gửi thật.

**Nghiệm thu:** email sai/mật khẩu sai/form trống có lỗi rõ; đăng nhập xong về đúng intended URL; đăng xuất và phiên hết hạn dẫn về login; đổi hồ sơ không tác động form mật khẩu khác.

### 5.2. Workspace, thành viên, role — P0 · UI; P1 · UI+BE

- Workspace list có tìm kiếm, phân biệt cá nhân/nhóm, vai trò và hành động “Mở workspace”; có empty state tạo workspace theo quyền.
- Tổng quan workspace tập trung đường dẫn đi tiếp, không để chuyển Owner/rời/xóa là phần nổi bật nhất.
- Thành viên: bảng tên/email/role/status; khu vực lời mời đang chờ riêng; nhãn hết hạn và thu hồi rõ ràng.
- Sau mời: hiển thị kết quả đúng trạng thái gửi; link mời chỉ ở nơi được phép. Không thông báo email đã đến hộp thư khi local chỉ dùng mail log.
- Role editor nhóm quyền theo module, mô tả tác động; phân biệt role hệ thống và tùy chỉnh; không cho chọn quyền vượt phạm vi server cấp.
- Nêu rõ workspace membership và project membership là hai lớp. Được vào workspace chưa chắc được xem mọi project.
- Chuyển Owner: modal ghi người nhận, tác động lên chủ cũ và project rights; xác nhận rõ trước POST. Rời/xóa dùng giao diện riêng, không trộn với sửa tên.
- P1 cần BE: phân trang/tìm thành viên hoặc tìm quyền khi dữ liệu lớn; không tải toàn bộ danh sách vào JS để giả phân trang.

**Nghiệm thu:** invite đúng/sai email, hết hạn, đã nhận, đã thu hồi; suspend làm mất truy cập đúng phạm vi; role tùy chỉnh không vượt quyền; đổi Owner có dữ liệu trước/sau.

### 5.3. Project và project member — P0 · UI

- Project overview có tên, trạng thái, deadline, đường dẫn tới task/lịch/plan/báo cáo và thành viên. Counts phải từ server hoặc không hiển thị nếu chưa có dữ liệu.
- Danh sách cho chuyển card/table, giữ tìm kiếm/lọc/sort/page trong URL.
- Project con có breadcrumb; không dùng hình cây để gợi ý có thừa kế quyền khi backend không thừa kế.
- Archive/restore, completed/cancelled phải nói rõ ảnh hưởng tới khả năng sửa task/lịch/plan; các thao tác bị chặn có lý do.
- Project member picker chỉ chọn workspace member hợp lệ; không hiển thị người đã suspended như lựa chọn mới hợp lệ.

**Nghiệm thu:** project archived vẫn có thể đọc theo quyền nhưng không xuất hiện nút ghi sai; project con không mở được chỉ vì có quyền project cha; giữ dữ liệu khi không được phép xóa vật lý.

### 5.4. Danh sách và cây task — P0 · UI; P1 · UI+BE

- Filter bar rút gọn gồm keyword/status/assignee/priority; các lựa chọn ít dùng đưa vào “Bộ lọc thêm”. Có nút xóa lọc và chips mô tả bộ lọc đang bật.
- Giữ `q/status/priority/assignee_id/archived/sort/direction/per_page/page` theo contract; đổi filter/sort cần về page1. Phân trang không mất bộ lọc.
- Cột chính: mã/tiêu đề, trạng thái, người làm, ưu tiên, hạn. Các cột nâng cao có thể thêm sau khi controller cấp dữ liệu; không query từng dòng trong Blade.
- Dòng mobile hiển thị tiêu đề và hành động mở trước, metadata xuống dòng. Không thu nhỏ chữ cả bảng để nhét vào màn hình.
- Cây task có mở/thu GROUP, indentation tối đa3 tầng, ký hiệu loại task, tiến độ giải thích được. Tùy chọn giữ trạng thái mở/thu chỉ lưu ID không nhạy cảm sau khi cân nhắc phạm vi workspace.
- Kéo thả parent/order là **UI+BE** nếu chưa có endpoint đáp ứng đầy đủ; không chỉ đổi DOM rồi báo đã lưu. Backend phải kiểm scope, depth, cycle, GROUP, trạng thái và expected version.
- Filter “Việc của tôi”, “Chờ tôi duyệt”, “Quá hạn”, “Đang bị chặn” nên là preset query; một số preset cần BE mới, không tự lọc trên một trang phân trang rồi gọi đó là toàn bộ dữ liệu.
- Kanban là P2: cần cơ chế truy vấn cột/phân trang, quyền chuyển trạng thái và kiểm workflow. Không kéo REVIEW sang DONE để bỏ qua reviewer, blocker, dependency hoặc timer.

**Nghiệm thu:** nhiều hơn một trang dữ liệu; lọc không ra kết quả khác với hệ thống chưa có task; tree có GROUP trống và đủ3 tầng; task hủy không làm tiến độ sai; reload/Back không mất scope.

### 5.5. Tạo/sửa và chi tiết task — P0 · UI+BE

- Form chia: nội dung → loại/cha → ưu tiên/ước lượng/hạn → phân công/reviewer. Nhóm nâng cao có thể thu gọn nhưng lỗi bên trong phải tự mở.
- GROUP không hiện estimate/assignee/reviewer như WORK. MILESTONE không có estimate. Khi đổi loại, giải thích dữ liệu nào không còn phù hợp và để server quyết định khả năng đổi.
- Gõ thời lượng “1 giờ30 phút” chỉ là tiện ích nhập; giá trị gửi vẫn phút nguyên đúng contract. Chưa có estimate hiển thị “Chưa ước lượng”, không thành0.
- Assignee/reviewer chọn theo tên, avatar/chữ cái, email khi phù hợp; UUID chỉ phục vụ value. Reviewer picker cần danh sách có task.review từ server.
- Chi tiết task: header mã/tên/status; hành động tiếp theo; mô tả; kế hoạch/thực tế; dependency/blocker; activity/file. Mobile ưu tiên phần hành động và blocker trước lịch sử dài.
- Review: phân biệt “Nộp duyệt”, “Duyệt hoàn thành”, “Yêu cầu sửa”, “Hủy”, “Mở lại”. Dialog lý do khi bắt buộc; không dùng một select status cho mọi quyết định.
- Hiển thị vì sao chưa thể bắt đầu: blocker mở, predecessor chưa DONE, lag chưa hết, project đóng, timer chưa dừng hoặc thiếu quyền. Nếu backend hiện chỉ trả lựa chọn/nội dung lỗi khi POST, cần bổ sung DTO các hành động và lý do có thể hiển thị an toàn.
- P0 BE: bổ sung chống ghi đè khi hai người cùng sửa metadata. Chọn version tăng dần hoặc token từ dữ liệu gốc được server xác minh trong transaction; không chỉ so updated_at có độ chính xác thấp. TaskService::save hiện cần được nâng cấp cho hợp đồng này trước khi FE gửi expected version.

**Nghiệm thu:** A/B cùng mở task; A lưu rồi B lưu nội dung cũ phải nhận lỗi stale và giữ bản nhập để đối chiếu. Workflow vẫn chặn sai reviewer, FS/lag, blocker, timer; không có thay đổi DB khi POST thất bại.

### 5.6. Blocker, dependency, activity và file — P0/P1 · UI

- Dependency ghi “Công việc này chờ … hoàn thành”, kiểu FS và lag theo phút/giờ dễ hiểu; link đến predecessor nếu còn quyền.
- Blocker phân biệt đang mở/đã xử lý, người báo/thời điểm, lý do và hành động xử lý theo quyền. Chưa có trường ghi giải pháp riêng thì không tự dựng đã lưu giải pháp.
- Activity có loại rõ: bình luận, tệp, đổi trạng thái, phân công, kế hoạch, timer, commit. Hiển thị before/after bằng nhãn người dùng; UUID không phải nội dung chính.
- Chỉnh/xóa comment chỉ hiện đúng người có quyền; lịch sử hệ thống không có nút sửa/xóa. Nội dung luôn escape.
- Upload ghi giới hạn5MiB và định dạng được backend chấp nhận; chọn file rồi hiển thị tên/kích thước trước khi gửi. Không nói có tiến độ upload phần trăm nếu chỉ là animation.
- File download tiếp tục qua route có quyền; không biến private storage thành URL public. Office/ZIP kiểm tra nội dung là việc backend.
- Gỡ attachment cần giải thích dữ liệu metadata đã gỡ và chính sách giữ byte hiện tại; không dùng nhãn “xóa vĩnh viễn” nếu thao tác không làm vậy.
- Mention, reaction, editor rich text, preview tài liệu là P2/UI+BE: cần xử lý quyền, sanitize/escape, notification recipient và giới hạn file trước.

### 5.7. Calendar và Timeline — P1 · UI; tương tác ghi cần UI+BE khi đổi contract

- Calendar có Today, trước/sau, ngày/tuần/tháng/danh sách; nhãn range và timezone cố định dễ thấy. Giữ giới hạn range mà service cho phép.
- Phân biệt TASK_BLOCK, MEETING, BUSY, NOTE, EXTRA_AVAILABLE bằng icon/nhãn và legend. Trạng thái cancelled/completed không giống planned.
- All-day cần hướng dẫn ngày kết thúc không bao gồm. Không dịch giao diện “đến hết ngày” rồi gửi ngày kết thúc hiện tại sai contract.
- Khi click event, mở chi tiết quyền hiện tại; trường hợp chỉ được xem busy thì chỉ hiện khoảng bận, không lộ title/task/user metadata ngoài projection được cấp.
- Tạo slot từ task nên prefill task/scope/title hợp lệ. Thao tác vẫn đặt lịch cho chính người tạo theo quy tắc hiện tại.
- Conflict báo khoảng giờ không hợp lệ và cách sửa; không tiết lộ tên sự kiện private ở workspace khác chỉ để giải thích trùng lịch.
- Timeline có trục ngày/giờ, mốc hôm nay, hàng task và khoảng được clip theo range. Thanh biểu thị TASK_BLOCK thực, không tự tính ngày từ estimate hoặc phần trăm tiến độ.
- Drag/resize: nếu bổ sung, phải gửi về CalendarService với version token và timezone đúng; khi lỗi phục hồi vị trí cũ, hiển thị lỗi, không ghi trực tiếp DB từ endpoint phụ bỏ qua kiểm tra.
- Zoom/scroll trên mobile không chặn thao tác cuộn trang. Có danh sách thay thế để thao tác bằng bàn phím.

**Nghiệm thu:** hai slot kề nhau hợp lệ, chồng một phút bị chặn; private busy không lộ nội dung; ngoài range không dựng event giả; DST lặp/không tồn tại bị xử lý đúng; lỗi stale không làm thanh nằm sai chỗ.

### 5.8. Plan editor thay JSON — P0 · UI+BE

Đây là hạng mục UX nên làm trước để người dùng phổ thông sử dụng được toàn bộ workflow.

1. **Thông tin:** tên, project/personal scope rõ, tạo mới hay phiên bản tiếp theo, nguồn manual/local/AI nếu backend cấp được đúng nguồn.
2. **Cây công việc:** thêm GROUP/WORK/MILESTONE; thêm con, sửa title/description; priority, estimate, assignee, reviewer bằng field chuẩn. Sinh ref ổn định trong bản nháp, không dùng vị trí dòng làm ID.
3. **Dependency:** chọn predecessor/successor theo tên và ref; lag; cảnh báo self/cycle trước submit. Server vẫn xác minh graph đầy đủ.
4. **Slot:** chọn WORK, thời điểm, timezone workspace; cảnh báo slot không thuộc người apply. Tách “Có kế hoạch công việc” và “Đã xếp lịch”, không bắt mọi task đều có slot.
5. **Rà soát:** tổng task theo loại, phút ước lượng, số chưa ước lượng, số dependency/slot; các cảnh báo. Những tổng preview là tạm tính từ draft, không thay report chính thức.
6. **Lưu nháp:** serialize đúng PLAN_PAYLOAD.md vào field `payload`; gửi name/project_id/base_id/_token theo form hiện có; redirect tới plan.show.

Cần BE hỗ trợ trình bày:

- Danh sách task hiện có đủ quyền và reviewer hợp lệ để chọn `task_id`; có tìm kiếm server nếu lớn. Endpoint mới nếu cần là route web có auth, chưa mặc định chuyển toàn dự án sang API.
- Ánh xạ lỗi `tasks.2.title`, `dependencies.0.predecessor`… về ref/field trong editor. Thay đổi thứ tự dòng không được làm lỗi gắn nhầm.
- Dữ liệu controller có đủ tên hiển thị, trạng thái task hiện có và lý do không sửa được; không render dữ liệu private rồi chỉ ẩn bằng CSS.
- Một nguồn xác định constraints như max100task/300dependency/300slot/depth3 để FE hiển thị; backend là nơi kiểm tra cuối cùng.

Giữ vùng JSON trong “Nâng cao” để debug/import có chủ đích, không bắt mọi người nhập JSON/UUID. Không thêm nút tải JSON lên rồi mặc định tin nội dung; các trường ngoài whitelist phải tiếp tục bị từ chối.

**Nghiệm thu:** tạo cây3 tầng qua UI, thêm FS/lag và slot, lưu rồi mở lại đúng dữ liệu; lỗi ở dòng cuối không làm mất các dòng trước; xóa task có tham chiếu cảnh báo và yêu cầu xử lý dependency/slot; thay title vẫn giữ ref; tạo revision từ applied map không nhân đôi task.

### 5.9. So sánh, duyệt và áp dụng plan — P0 · UI+BE

- Header luôn có version, trạng thái duyệt, đã/chưa apply, active hay superseded và thời điểm/người duyệt khi được phép.
- Diff nhận diện theo task_id nếu có, sau đó ref trong lineage; không ghép các task chỉ vì trùng title. Dòng mới chưa có task_id không được nhận là task đã tồn tại.
- Bảng thay đổi: thêm mới, cập nhật title/estimate/assignee/reviewer/parent, dependency thêm, slot thay. “Bỏ khỏi đề xuất” không ghi “Đã xóa task” vì apply hiện không xóa dữ liệu cũ khi thiếu dòng.
- Trước apply hiển thị tác động: task nào sẽ tạo/cập nhật, lịch nào có thể thay, quy tắc chỉ sửa TODO, ai sở hữu slot và bản active nào sẽ bị supersede.
- Hiện chưa có preview backend đầy đủ cho mọi tác động. Cần bổ sung phương thức preview dùng chung validator/quy tắc với apply; preview không ghi DB. Preview chỉ là thông tin tại thời điểm đọc, apply vẫn kiểm lại trong transaction.
- Nút propose/approve/reject/apply theo quyền server và expected_status; apply đã thực hiện thì không hiện lại như hành động mới. Reject yêu cầu lý do.
- Stale snapshot: cho xem thông báo nguồn thay đổi, mở nguồn nếu có quyền, tạo revision mới từ bản phù hợp. Không có nút “bỏ qua kiểm tra và áp dụng” mặc định.
- Applied payload bất biến; mọi sửa tạo version khác. So sánh và sửa không âm thầm reset is_active hoặc thay lịch sử reviewed/applied.

**Nghiệm thu:** approve chưa tạo task/lịch; apply một lần tạo đúng, gửi lại không trùng; hai người quyết định trạng thái có stale guard; source thay đổi thì lỗi và không ghi một phần; lỗi slot sau các task đã được tạo trong transaction vẫn rollback hết.

### 5.10. AI và trợ lý blocker — P0/P1 · UI; KEY cho kết nối thật

- Hiển thị rõ loại thao tác: lập kế hoạch, điều chỉnh kế hoạch, hỗ trợ blocker, nhận xét báo cáo. Replanning cần chọn base phù hợp.
- Cho biết phạm vi dữ liệu được gửi, giới hạn context, trạng thái AI đang tắt/chưa cấu hình; không để người dùng nhập provider key vào form prompt.
- Queued/running là trạng thái chờ, không phải thành công. Không dựng phần trăm tiến độ hoặc thời gian còn lại khi worker không có dữ liệu đó.
- Trang chi tiết có làm mới, thời điểm, lỗi an toàn, cancel/retry theo quyền và lượt còn lại. Retry dùng context cũ; dữ liệu mới cần request mới.
- Có thể thêm GET polling giới hạn khi queued/running nếu cần: dừng khi terminal, khi tab ẩn hoặc người dùng rời trang, giảm tần suất khi lỗi. Không POST lại để “kiểm tra trạng thái”. Cần thiết kế định dạng response nếu không reload toàn trang.
- Kết quả planning/replanning dẫn tới draft để người dùng rà soát, không gọi apply tự động. Assistance không có nút “đã xử lý blocker” nếu backend chỉ trả tư vấn.
- Text AI/markdown, nếu hỗ trợ, phải được render an toàn; không thực thi HTML/script/link action từ nội dung phản hồi.

**Nghiệm thu:** AI tắt → queued → worker → failed có lý do thật; cancel rồi response về muộn không tạo draft; mất quyền trước/sau provider được chặn; schema sai không tạo business data. Thành công provider thật chỉ kiểm sau cấu hình, không dùng mock output làm minh chứng.

### 5.11. Dashboard, report và CSV — P1 · UI

- KPI: WORK đang mở, quá hạn, blocked, chờ duyệt, hoàn thành trong kỳ, planned/actual. Nếu aggregate chưa có thì đề nghị BE cấp; không đếm toàn bộ dữ liệu từ một trang task.
- Chia hai vùng “Hiện trạng tại …” và “Kết quả trong khoảng …”. Không gán status hiện tại thành status lịch sử trong kỳ.
- Chart từ metrics server: phân bổ status, workload theo người, planned/actual. Có bảng số liệu tương đương; không chỉ dựa màu hoặc tooltip chuột.
- Phút → giờ/phút để đọc dễ, nhưng không đổi cách tính. Ví dụ0.11 phút xấp xỉ6.6 giây, không hiển thị thành0.11 giờ. `actual=null` là chưa có dữ liệu; `actual=0` là có giá trị0.
- Mỗi snapshot có người tạo/thời điểm/kỳ/timezone/bộ lọc và nhãn “Số liệu đã lưu”; nút tạo bản mới thay cho nút sửa số liệu cũ.
- CSV/bản in dùng snapshot cố định, đúng quyền; giải thích khi snapshot không còn xem được do quyền nguồn thay đổi. Không giữ report ẩn trong cache trình duyệt để vượt quyền mới.
- Trend theo ngày, burn-down, tỷ lệ trễ lịch sử là **UI+BE**: phải có nguồn dữ liệu và định nghĩa lịch sử đủ tin cậy; không vẽ đường giả từ snapshot hiện tại.

### 5.12. Timer và giờ thực tế — P0 · UI+BE; P1 cho quản lý sai sót

- Thanh timer toàn cục chỉ của chính người dùng, có elapsed, nút stop, tên/link task khi còn quyền.
- Elapsed tính từ started_at do server cấp, không từ số giây đã mở trang; reload/chuyển tab không làm timer về0. Không tạo time_entry mới khi reload.
- Khi mất quyền task, hiển thị thông tin chung và vẫn cho stop clock của mình theo route hiện có; không lộ title trong HTML/data attributes.
- Nếu REVIEW/DONE bị chặn vì timer, đưa người dùng tới đúng hành động dừng; không tự dừng và chuyển trạng thái chỉ vì đóng modal/trang.
- P1: phiên quá dài có cảnh báo, ghi chú/đề nghị điều chỉnh. Việc sửa started_at/ended_at, nhập giờ thủ công hoặc phê duyệt chỉnh giờ là UI+BE/DB theo thiết kế; cần audit giá trị trước/sau, quyền và chống chồng lấn. Chưa có route thì không dựng nút hoạt động giả.
- Không tự stop khi đóng trình duyệt, vì không thể chắc người dùng đã ngừng làm; chốt chính sách xử lý clock quên dừng trước khi bổ sung automation.

### 5.13. Thông báo, settings và GitHub — P1 · UI/UI+BE/KEY

- Inbox có chưa đọc/đã đọc, loại sự kiện, thời điểm, link đích theo quyền; đọc một/đọc tất cả dùng form thật. Không đánh dấu read trước khi server xác nhận.
- Preference in-app hiện có; email/Telegram chưa có delivery thật thì hiển thị chưa hỗ trợ/chưa cấu hình, không tạo toggle trông đã bật gửi.
- Ngôn ngữ hiện là preference; nếu muốn tiếng Anh đầy đủ, cần chuyển chuỗi vào lang files và rà toàn bộ thông báo validation/server. Đổi thuộc tính lang chưa phải đã dịch giao diện.
- GitHub config không hiện lại secret; để trống nghĩa giữ secret cũ. Hiển thị global flag và connection state riêng, hướng dẫn repository ID/webhook URL/content type.
- Log có delivery/time/event/status/summary; nội dung commit theo task marker, không tự DONE task. Thêm trạng thái nhận gần nhất/không có lượt nhận sau cấu hình cần BE cấp dữ liệu.
- Bật connection không chứng minh GitHub public đã gửi thành công. Chỉ báo kết nối đã xác minh khi có bằng chứng delivery thật phù hợp; không gửi webhook thử tới host tùy ý từ input người dùng.

## 6. Chức năng backend nên bổ sung sau vòng UI cốt lõi

| ID | Đề xuất | Loại | Quy tắc cần chốt / hướng xử lý | Nghiệm thu dữ liệu |
|---|---|---|---|---|
| F01 | Chống ghi đè metadata Task/Project ở nhiều tab | P0 · UI+BE, DB nếu dùng version column | Kiểm expected version trong transaction, trả lỗi stale và giữ form; áp dụng nhất quán cho các màn hình cập nhật quan trọng | Người thứ2 không ghi đè im lặng; DB giữ bản người thứ1 |
| F02 | Preview tác động apply plan | P0 · UI+BE | Cùng validator/quy tắc apply, read-only; không cam kết preview thay thế recheck | Preview không tạo task/event/activity; apply vẫn chặn source đã đổi |
| F03 | Bộ dữ liệu editor plan và lỗi theo ref | P0 · UI+BE | Task/user/reviewer có quyền, giới hạn truy vấn, mapping lỗi ổn định | Không chọn UUID ngoài scope; sắp lại dòng không gắn lỗi sai |
| F04 | “Việc của tôi / Chờ tôi duyệt / Đang blocked” | P1 · UI+BE | Server query, scope người hiện tại, phân trang thật; không cấp task ngoài project được xem | Đúng cả khi có nhiều trang; thu hồi quyền là biến mất |
| F05 | Lưu bộ lọc cá nhân | P1 · UI+BE, DB tùy cách lưu | Whitelist query; riêng user; không lưu credential; revalidate quyền khi mở | Filter cũ không truy xuất được project đã mất quyền |
| F06 | Giao/đổi priority/archive hàng loạt | P1 · UI+BE | Chọn rõ all-or-nothing hay partial; kiểm từng task và expected version; không đưa review/DONE hàng loạt vào bản đầu | Một task không có quyền không làm cập nhật nhầm; kết quả nêu từng lỗi hoặc rollback đúng cam kết |
| F07 | Nhắc hạn/chờ review | P1 · UI+BE, KEY nếu gửi ngoài | Database job, timezone, preference, dedupe theo sự kiện/ngày; chỉ nhắc người còn quyền | Worker lặp không gửi trùng; task hoàn tất/thu hồi quyền không nhận nhắc mới |
| F08 | Theo dõi hạn và nguyên nhân trễ | P1 · UI+BE | Định nghĩa quá hạn WORK, dependency impact, lịch sử đổi deadline nếu muốn báo cáo lịch sử | Cảnh báo từ nguồn thật, không tự suy hoàn thành từ slot |
| F09 | Kiểm tra vận hành queue | P1 · UI+BE | Trang vận hành có quyền riêng; run/call/job trạng thái an toàn; không hiển thị secrets/payload toàn cục | User thường không xem run của người khác; retry vẫn theo giới hạn |
| F10 | Điều chỉnh time entry có audit | P1 · UI+BE/DB | Owner của giờ gửi đề nghị hoặc sửa theo chính sách; reason, before/after, overlap và actor audit | Không mất lịch sử gốc, báo cáo cập nhật đúng theo định nghĩa đã chọn |
| F11 | Calendar/team scheduling | P2 · UI+BE/DB có thể cần | Quyết định quyền đặt lịch thay người khác, consent/visibility/conflict và notification; không bỏ guard chỉ-own hiện có | Lịch private không lộ; hai người đặt cùng slot được serialize |
| F12 | Giờ làm tùy chỉnh | P2 · UI+BE/DB có thể cần | Một nguồn config có override rõ; không duplicate default ở nhiều bảng; snapshot giữ quy tắc lúc lập kế hoạch | Plan cũ không đổi cách hiểu khi config mới đổi |
| F13 | Watcher/mention hoặc nhiều người làm | P2 · UI+BE/DB | Chốt một người chịu trách nhiệm chính, người theo dõi, quyền review và notify; không biến assignee_id thành mảng từ FE | Membership bị thu hồi chặn đọc/notify; report không đếm trùng |
| F14 | Recurring task/template | P2 · UI+BE/DB | UUID mới, không clone DONE/actual/history, dependency mapping, timezone và chống tạo lặp | Scheduler chạy2 lần không tạo2 bộ task cùng kỳ |
| F15 | Tìm kiếm xuyên project | P2 · UI+BE | Tìm trên tập được phép xem; chống lộ cả tiêu đề/snippet/count | User khác quyền thấy kết quả/count khác đúng phạm vi |

Các đề xuất F11–F15 là mở rộng, không phải điều kiện để bản hiện tại được mô tả là có thêm các tính năng đó. Không nên triển khai cùng lúc trước khi P0/P1 cốt lõi được kiểm lại bằng dữ liệu.

## 7. Hiệu năng, dữ liệu và khả năng bảo trì

- Đo trước khi tối ưu: thời gian response, số query, kích thước HTML trên bộ dữ liệu review được tạo có nhãn. Không công bố benchmark khi chưa đo.
- Danh sách/cây/member selectors cần giới hạn rõ. Server pagination khác với giấu bớt DOM. Khi thêm counts/widgets, eager load/aggregate để tránh query theo từng dòng.
- Cây rất lớn: quyết định lazy load theo nhánh hoặc giới hạn project; không tải mọi task rồi làm dashboard/search tại client.
- Bộ lọc và URL dùng whitelist hiện có; không nhận tên cột/order raw từ UI.
- Fragment/cache phải có scope actor/workspace/project và thay đổi quyền. Không dùng cache chart/report chung giữa người khác quyền.
- Tách CSS thành tokens/base/components/pages khi hợp lý; giữ một cách đặt tên và entrypoint rõ. Nếu đưa Vite vào sau, cập nhật hướng dẫn build/deploy và kiểm bản production, không để trang chạy chỉ vì dev server của người làm FE còn mở.
- Dữ liệu trình bày như nhãn trạng thái, action availability và menu dùng chung; không sao chép bộ quy tắc ở nhiều trang.
- Thay đổi schema bằng migration bổ sung; không reset database đang dùng. Không đưa `.env`, APP_KEY, DB live, cookies và private files vào gói FE/commit.

## 8. Responsive và accessibility: tiêu chí chung

| Trường hợp | Yêu cầu nghiệm thu |
|---|---|
| 360px/390px | Không tràn ngang toàn trang; nếu bảng/Timeline cần cuộn có vùng cuộn rõ; hành động chính dùng được |
| 768px | Drawer/sidebar không che form không có cách đóng; bảng và filter không chồng nhau |
| 1280px/1440px | Nội dung có phân cấp, form không kéo quá rộng, vùng dữ liệu tận dụng không gian |
| Zoom200% | Không mất nội dung/nút; thông báo lỗi và action vẫn truy cập được |
| Bàn phím | Tab theo thứ tự; focus thấy rõ; dropdown/dialog mở/đóng được; không keyboard trap |
| Form | Label liên kết đúng input; ID không trùng; lỗi gắn field, summary có thể điều hướng |
| Màu | Trạng thái có chữ/icon; contrast được đo bằng công cụ kiểm tra; dark/light cùng được rà |
| Chart/Timeline | Có số liệu hoặc danh sách thay thế, tooltip không chỉ mở bằng hover |
| Reduced motion | Animation không bắt buộc để hiểu kết quả; hỗ trợ giảm chuyển động |
| Nội dung dài | Title200 ký tự, email/UUID/commit dài và comment nhiều dòng không làm vỡ card |

## 9. Trạng thái bắt buộc phải thiết kế

Mỗi list/form/module cần có: loading khi có request bất đồng bộ, chưa có dữ liệu, không có kết quả sau lọc, thành công, lỗi validation, thiếu quyền, dữ liệu không tồn tại, session hết hạn, request quá nhanh, mạng lỗi hoặc worker chưa xử lý.

| Tình huống | Thông báo/hành động đúng |
|---|---|
| Không có task nào | Giải thích và nút tạo theo quyền |
| Filter không ra task | Hiển thị bộ lọc và nút xóa lọc, không gợi ý DB rỗng |
| 403 | Không còn quyền; về scope hợp lệ; không hiện chi tiết dữ liệu bị cấm |
| 404 | Không tìm thấy hoặc đối tượng không thuộc scope; link quay lại an toàn |
| 410 invitation | Hết hạn/thu hồi/đã dùng theo kết quả backend; hướng dẫn xin lời mời mới |
| 419 | Phiên/form hết hạn; tải lại/đăng nhập; không tự gửi POST cũ |
| 429 | Đã gửi quá nhanh; cho thử sau theo dữ liệu server nếu có, không dựng countdown giả |
| Plan stale | Nguồn đã thay đổi, rà soát và tạo version mới |
| Calendar conflict | Chọn khoảng khác; không tiết lộ lịch private gây trùng |
| AI queued quá lâu | Kiểm worker/trạng thái, cho làm mới/hủy theo quyền; không hiện succeeded |
| Snapshot mất quyền nguồn | Giải thích quyền thay đổi; không quay sang metrics live để giả thay snapshot |

Mẫu microcopy nên dùng: “Đã lưu bản nháp, chưa thay đổi công việc hoặc lịch”; “Đã duyệt, chưa áp dụng”; “Dữ liệu đã thay đổi từ lúc lập kế hoạch. Hãy tạo phiên bản mới để rà soát”; “Chưa có phiên làm việc đã kết thúc trong kỳ”.

## 10. Thứ tự triển khai đề xuất

Không ấn định số ngày khi chưa biết năng lực/người phụ trách. Các đợt dưới đây là thứ tự phụ thuộc, có thể chia nhỏ thành task/PR.

| Đợt | Công việc | Kết quả để kiểm tra |
|---|---|---|
| A · P0 nền UI | Sửa chỉ dẫn tài liệu, app shell/scope/breadcrumb, tokens/components, labels/errors/mobile/auth | Một luồng login → workspace → project → task sử dụng thuận tiện ở desktop/mobile |
| B · P0 nghiệp vụ task | List/tree/detail/review/blocker/file, timer toàn cục, F01 chống ghi đè | Làm việc thật từ TODO đến DONE, giờ thực tế và quyền không sai |
| C · P0 kế hoạch | F03/editor, diff, F02/preview, đề xuất/duyệt/apply/stale/conflict | Người không biết JSON vẫn lập và áp dụng được kế hoạch có cây/FS/slot |
| D · P1 quan sát | Calendar/Timeline, dashboard/chart/report/CSV, notifications, AI state, GitHub state | Đọc đúng thời gian/số liệu và nhận biết lỗi/chưa cấu hình |
| E · P1 tiện ích | F04 presets, chọn lọc F05–F10; query/index theo số đo | Ít thao tác lặp hơn, không làm chậm danh sách hoặc lộ dữ liệu |
| F · P2 mở rộng | Chọn từng nhu cầu F11–F15 sau thống nhất nghiệp vụ | Migration/flow/định nghĩa quyền đầy đủ, có bằng chứng thật trước khi công bố |

## 11. Mẫu ticket giao việc cho AI/người phát triển

Mỗi ticket nên có: ID, vấn đề người dùng, màn hình/Blade, trạng thái backend hiện có, route/method/fields, quyền, mô tả UI, trường hợp lỗi, phần BE/DB cần thêm, tiêu chí nghiệm thu và minh chứng sẽ lưu.

### Ticket mẫu UI-PLAN-01 — Trình soạn kế hoạch không cần JSON

- **Vấn đề:** người dùng đang phải tự nhập ref/UUID/JSON để tạo kế hoạch.
- **Nguồn:** `resources/views/plans/create.blade.php`, `PlanController::create/store`, `PlanService::validate/create`, `docs/PLAN_PAYLOAD.md`.
- **Quyền:** plan.create trong scope hợp lệ; revision cần quyền tương ứng; người nhìn thấy editor không được tự mở rộng task.assign/plan.approve.
- **Đầu ra:** bảng/cây editor, task/user/reviewer pickers, dependency/slot editor; vùng JSON nâng cao; submit form hiện có đúng payload.
- **BE cần bổ sung:** dữ liệu picker và action eligibility đã lọc quyền; mapping lỗi theo ref; giới hạn nguồn; không đổi lifecycle apply.
- **Không tự thực hiện:** gọi provider AI, tạo task khi chưa apply, đổi schema DB chỉ để FE lưu tạm, lưu secrets hoặc dữ liệu công việc vào localStorage mặc định.
- **Nghiệm thu:** tạo GROUP +2WORK +1FS +2slot; đổi thứ tự/title không đổi ref; lỗi không mất nhập; mở revision applied dùng task_id cũ; Viewer không thấy nút ghi; reload đọc lại đúng dữ liệu đã lưu.
- **Minh chứng:** ảnh trước/sau, URL/form sử dụng, danh sách UUID từ DB trước/sau, lỗi cycle/scope và kết quả đọc lại; không dùng TestCase/mock làm bằng chứng thay thế.

## 12. Bộ flow nghiệm thu sau cải thiện

| Flow | Dữ liệu và thao tác thật | Điều cần chứng minh |
|---|---|---|
| Người làm | Owner giao WORK cho Member → Member IN_PROGRESS → timer → stop → REVIEW → reviewer DONE | UI chỉ hành động hợp lệ; actual từ TimeEntry, không từ estimate |
| Bị chặn | A→B có lag, B có blocker; thử start B; xử lý blocker/A nhưng chưa đủ lag | Lý do đúng, không đổi status khi bị chặn |
| Phân quyền | Owner/Manager/Member/Viewer/outsider; workspace có2project khác quyền | Menu/data/action đúng; gọi route trực tiếp vẫn bị BE chặn |
| Sửa đồng thời | Hai session mở cùng task/project trước khi sửa | Không ghi đè im lặng sau khi F01 có thật |
| Kế hoạch | Tạo/editor → save draft → compare → propose → approve → preview/apply → read-back | Draft/approve chưa đổi task; apply transaction; ref/UUID không trùng |
| Plan lỗi | Source đổi, slot trùng ở dòng sau, cycle, UUID ngoài scope, gửi apply2lần | Stale/validation/scope bị chặn; rollback toàn bộ; không tạo bản sao |
| Lịch | Slot kề/trùng, all-day, timezone/DST, private busy xuyên workspace | Đúng biên giờ, không lộ tên/nội dung private |
| Timer mất quyền | Start clock → suspend membership → mở timer và stop | Không lộ task nhưng vẫn dừng clock của mình; người khác không stop được |
| Report | Lưu snapshot → đổi estimate/task → mở bản cũ và tạo bản mới → CSV/print | Bản cũ bất biến, bản mới đúng nguồn, null/0/phút/giờ không nhầm |
| AI | Disabled/config lỗi → queue → worker → terminal; khi có key kiểm provider thật riêng | Không success giả; cancel/stale/quyền vẫn được kiểm |
| GitHub | Signed local hoặc delivery thật đã cấu hình → lặp delivery → đổi repo/disable | Không trùng log/activity, không đổi status task, secret không hiển thị |
| Responsive | Lặp các hành động chính ở mobile/desktop, keyboard và zoom | Chức năng dùng được, focus/lỗi/scroll hợp lý, không chỉ chụp ảnh trang tĩnh |

Tạo dữ liệu review có nhãn, lưu bằng chứng vào thư mục verification mới. Không reset DB đang dùng, không chỉnh trực tiếp DB để làm ảnh khớp UI. Nếu chuẩn bị dữ liệu bằng CLI thì ghi rõ bước đó; phần được công bố là flow người dùng phải thật sự đi qua route/controller/service.

## 13. Definition of Done cho mỗi phần

- Có trang/luồng rõ ràng, đầy đủ normal/empty/error/no-permission/stale khi liên quan.
- Route/method/CSRF/fields không bị phá; endpoint mới có validation và kiểm quyền trong scope.
- Dữ liệu đọc lại khớp thao tác; tác dụng phụ và rollback đúng cam kết.
- Cả desktop/mobile và bàn phím thực hiện được thao tác chính; lỗi không làm mất dữ liệu nhập.
- Không có fake success, fake AI, fake progress, biểu đồ bịa hoặc số liệu tính lại khác backend.
- Không lộ private event/task/file/secret qua HTML, JS, tooltip, cache hoặc URL.
- Cập nhật FRONTEND_HANDOFF/contract và ghi rõ chức năng nào thực sự mới có backend.
- Lưu flow thật và lỗi đã sửa; không thay minh chứng bằng test class.

## 14. Chỉ dẫn bắt đầu cho AI tiếp theo

Đọc theo thứ tự: `DELIVERY_STATUS.md` → `FRONTEND_HANDOFF.md` phần đầu mới và “Bàn giao cuối” → tài liệu này → `PLAN_PAYLOAD.md`/`AI_PROVIDER_CONTRACT.md` → Blade/controller/service liên quan tới ticket.

Bắt đầu từ đợt A, sau đó B/C. Với mỗi ticket, xác nhận trong source route/field/quyền thực sự tồn tại; đừng dựa vào mô tả lịch sử của phần2–6 để dựng thêm endpoint giả. Khi thiếu dữ liệu cho UI, bổ sung contract backend rõ ràng rồi mới nối tương tác. Phạm vi lượt này chỉ là tài liệu; các đề xuất ở trên chưa được triển khai tự động.

## 15. Bàn giao thiết kế giao diện (UI/UX) và các ghi chú Backend (18/10/2026)

### 15.1. Tổng thể hoàn thiện UI/UX
Toàn bộ hệ thống giao diện Frontend (gồm hơn 35 view Blade và hệ thống CSS/JS) đã được đại tu toàn diện, đảm bảo tính thẩm mỹ cao, thân thiện và thuận tiện nhất cho người dùng:
1. **Design System & Theme Tokens (`public/css/modern-theme.css`)**:
   - Sử dụng typography hiện đại từ Google Fonts: font chính `Plus Jakarta Sans` và font mã/số liệu `JetBrains Mono`.
   - Bộ biến màu CSS tokens đồng bộ cho cả Light Mode và Dark Mode (hỗ trợ chuyển đổi mượt mà qua thuộc tính `data-theme="dark"`).
   - Hiệu ứng đổ bóng card elevations, viền bo góc (`radius-sm`, `radius-md`, `radius-lg`), trạng thái hover và micro-animations khi thao tác nút bấm, thẻ công việc.
2. **Landing Page & Luồng Demo (`welcome.blade.php`)**:
   - Thiết kế trang chủ giới thiệu sản phẩm hoàn chỉnh với Hero Banner, các khối tính năng chủ đạo, thẻ thông tin kiến trúc MVC.
   - Thẻ thông tin tài khoản Demo (`demo1@example.test` / `Demo@123456`) với nút bấm 1-click tự động điền form đăng nhập.
3. **App Shell & Điều hướng toàn cục (`components/layout.blade.php`)**:
   - Thanh điều hướng trên cùng (Topbar) tích hợp bộ chọn Workspace, widget đếm giờ làm việc (Live Timer) toàn cục, chuông thông báo có đếm unread count, nút chuyển đổi giao diện sáng/tối và menu tài khoản.
   - Sidebar thu gọn/mở rộng mượt mà, phân cấp rõ ràng theo quyền hạn và phạm vi Workspace / Project.
4. **Quản lý công việc & Cây phân rã WBS (`tasks/index.blade.php`, `tasks/show.blade.php`, `tasks/tree.blade.php`)**:
   - Bổ sung thanh lọc nhanh preset chips (Tất cả, Của tôi, Cần duyệt, Bị chặn, Quá hạn), hỗ trợ tìm kiếm và lọc nâng cao đa tiêu chí.
   - Chi tiết công việc với quy trình chuyển trạng thái 1-click trực quan (kèm popup lý do nếu cần), thanh tiến độ hoàn thành cho task lá, danh sách FS dependencies và phân tích hạn chót (Deadline insights).
   - Cây phân rã công việc WBS dạng thư mục/thẻ lồng nhau, phân biệt rõ biểu tượng Nhóm việc (Group), Công việc (Work), Cột mốc (Milestone).
5. **Lịch biểu & Trục thời gian Gantt (`calendar/index.blade.php`, `calendar/timeline.blade.php`)**:
   - Lưới lịch Tháng / Tuần / Ngày với đánh dấu Ngày hôm nay nổi bật, các thẻ sự kiện phân màu theo loại (`TASK_BLOCK`, `MEETING`, `BUSY`, `NOTE`).
   - Biểu đồ trục thời gian (Timeline Gantt) dạng cuộn ngang trực quan, có vạch giờ chi tiết và đường chỉ báo thời điểm hiện tại (`timeline-now`).
6. **Báo cáo phân tích số liệu (`reports/dashboard.blade.php`)**:
   - 4 thẻ KPI nổi bật (Tổng task, Tỷ lệ hoàn thành, Giờ thực tế, Công việc quá hạn), biểu đồ thanh phân bổ trạng thái và thước đo so sánh giờ ước lượng vs thực tế.
7. **Bảo toàn 100% hợp đồng dữ liệu Backend**:
   - Tuyệt đối không thay đổi các thuộc tính dữ liệu: `data-page`, `data-form`, `data-task-id`, `data-confirm`, CSRF tokens, `@method` verbs, named buttons, route names và các element IDs JavaScript (`#plan-editor`, `#payload-packed`, v.v.).

---

### 15.2. Ghi chú Logic Backend cho AI tiếp quản (Backend Backlog / Next Steps)
Dành cho AI hoặc kỹ sư backend triển khai các giai đoạn logic tiếp theo, dưới đây là các đề xuất tối ưu hóa nghiệp vụ:

| Module | Đề xuất cải tiến Logic Backend | File/Class Backend liên quan | Hướng dẫn triển khai |
|---|---|---|---|
| **Bulk Actions** | Xử lý hàng loạt qua Queue khi số lượng lớn | `TaskBulkController.php`, `TaskBulkService.php` | Hiện tại bulk actions xử lý đồng bộ trong 1 transaction DB. Khi người dùng chọn > 50 tasks (ví dụ đổi hạn hoặc gán người hàng loạt), backend nên kiểm tra nếu `count($taskIds) > 30` thì dispatch `BulkTaskUpdateJob` vào Queue và hiển thị flash message "Đang xử lý nền" thay vì xử lý trực tiếp HTTP synchronous tránh chạm giới hạn PHP execution time. |
| **Realtime Sync** | Server-Sent Events (SSE) cho Timer & Thông báo | `TimerController.php`, `NotificationController.php` | Hiện tại Timer đếm giây ở client dựa trên `started_at` do server cấp lúc tải trang. Để đồng bộ trạng thái timer và unread count khi người dùng mở nhiều tab hoặc dừng timer từ thiết bị khác, backend có thể tạo 1 route SSE nhẹ: `GET /workspaces/{workspace}/live-stream` (hoặc tích hợp Laravel Reverb) phát sự kiện `timer.started`, `timer.stopped`, `notification.received`. |
| **Calendar Reschedule** | API cập nhật nhanh khoảng giờ cho kéo thả | `CalendarEventController.php`, `CalendarEventService.php` | UI Lịch và Timeline đã sẵn sàng giao diện. Backend cần bổ sung action `PATCH /workspaces/{workspace}/calendar/{event}/reschedule` nhận `{ starts_at, ends_at }`, chạy qua validator kiểm tra quyền `calendar.manage`, xác thực không vượt quá giới hạn 93 ngày, và kiểm tra xung đột bận nếu sự kiện là bận riêng tư. Trả về JSON `{ success: true, event: ... }`. |
| **Lazy WBS Tree** | Nạp cây công việc phân tầng theo yêu cầu (Lazy Loading) | `TaskController::tree`, `TaskService.php` | Đối với các dự án lớn có hàng trăm tasks nhiều tầng, view `tasks.tree` hiện load toàn bộ cây đệ quy qua biến `$nodes`. Backend có thể bổ sung query parameter `?parent_id={id}` vào route hoặc endpoint `GET /workspaces/{workspace}/tasks/tree-branch` để trả về danh sách con trực tiếp của 1 nhóm khi người dùng bấm mở thẻ `<details>`. |
| **Plan Impact Simulation** | Mô phỏng xung đột tài nguyên nâng cao | `PlanPreviewController.php`, `PlanService::preview` | Bản preview kế hoạch hiện rollback trong transaction DB để kiểm tra tính hợp lệ cơ bản. Backend có thể bổ sung phần tính toán "Độ quá tải nhân sự" (Resource Over-allocation): so sánh tổng `estimate_minutes` của một người trong 1 ngày làm việc (ví dụ quá 8 tiếng) dựa trên `work_schedule` của user để cảnh báo trên UI Preview trước khi bấm Áp dụng. |
| **AI LLM Live Provider** | Cấu hình chìa khóa thật & Worker xử lý | `config/ai.php`, `AiRunService.php`, `AiWorkerJob.php` | Khi chủ dự án cung cấp API key thật (OpenAI / Google Gemini / Anthropic), cập nhật `.env` với `AI_ENABLED=true`, `AI_KEY=...`, `AI_ENDPOINT=...`. Giữ nguyên luồng xử lý bất đồng bộ qua Queue Worker (`php artisan queue:work`) để bảo toàn tính ổn định của giao diện. |

---

## 16. Biên bản Kiểm thử Tương tác Nút bấm & Toàn bộ Giao diện (19/10/2026)

Hệ thống đã trải qua đợt kiểm thử thực tế toàn diện 100% bằng dữ liệu sống trên môi trường cục bộ (`http://127.0.0.1:8123`) với tài khoản thật `demo1@example.test` thuộc Workspace `Demo Team A` và Project `Demo Work Management`. Không sử dụng mock DB hay kết quả test ảo.

### 16.1. Kết quả kiểm tra hiển thị 32/32 Giao diện chính (100% HTTP 200 OK)
Mọi trang đều được nạp đầy đủ qua Blade engine, không có bất kỳ lỗi cú pháp, thiếu biến hoặc ngoại lệ server:
1. `GET /workspaces` (200 OK) - Danh sách Không gian làm việc
2. `GET /workspaces/{id}` (200 OK) - Tổng quan Không gian làm việc
3. `GET /workspaces/{id}/edit` (200 OK) - Chỉnh sửa thông tin Workspace
4. `GET /workspaces/{id}/members` (200 OK) - Danh sách thành viên Workspace
5. `GET /workspaces/{id}/roles` (200 OK) - Quản lý Vai trò & Quyền hạn
6. `GET /workspaces/{id}/roles/create` (200 OK) - Tạo Vai trò mới
7. `GET /workspaces/{id}/operations` (200 OK) - Nhật ký vận hành hệ thống
8. `GET /workspaces/{id}/reminders` (200 OK) - Danh sách nhắc việc tự động
9. `GET /workspaces/{id}/projects` (200 OK) - Danh sách Dự án
10. `GET /workspaces/{id}/projects/{id}` (200 OK) - Tổng quan Dự án
11. `GET /workspaces/{id}/projects/{id}/members` (200 OK) - Thành viên Dự án
12. `GET /workspaces/{id}/projects/{id}/github` (200 OK) - Tích hợp kho mã nguồn GitHub
13. `GET /workspaces/{id}/projects/{id}/timeline` (200 OK) - Trục thời gian Gantt
14. `GET /workspaces/{id}/projects/{id}/tasks` (200 OK) - Bảng danh sách công việc
15. `GET /workspaces/{id}/projects/{id}/tasks/create` (200 OK) - Biểu mẫu tạo công việc mới
16. `GET /workspaces/{id}/projects/{id}/tasks/tree` (200 OK) - Cây phân rã công việc WBS
17. `GET /workspaces/{id}/projects/{id}/tasks/{id}` (200 OK) - Chi tiết công việc
18. `GET /workspaces/{id}/projects/{id}/tasks/{id}/edit` (200 OK) - Sửa công việc
19. `GET /workspaces/{id}/dashboard` (200 OK) - Dashboard báo cáo điều hành
20. `GET /workspaces/{id}/reports` (200 OK) - Danh sách Snapshot báo cáo
21. `GET /workspaces/{id}/ai-runs` (200 OK) - Lịch sử tác vụ AI
22. `GET /workspaces/{id}/ai-runs/create` (200 OK) - Gửi yêu cầu phân tích AI
23. `GET /workspaces/{id}/plans?project_id=...` (200 OK) - Danh sách kế hoạch
24. `GET /workspaces/{id}/plans/create?project_id=...` (200 OK) - Tạo bản thảo kế hoạch
25. `GET /workspaces/{id}/plans/{id}` (200 OK) - Chi tiết & đối chiếu kế hoạch
26. `GET /workspaces/{id}/plans/{id}/preview` (200 OK) - Xem trước mô phỏng áp dụng
27. `GET /workspaces/{id}/calendar` (200 OK) - Lịch biểu công việc
28. `GET /workspaces/{id}/calendar/create` (200 OK) - Tạo sự kiện lịch
29. `GET /timers` (200 OK) - Danh sách lượt bấm giờ
30. `GET /notifications` (200 OK) - Trung tâm thông báo
31. `GET /profile` (200 OK) - Hồ sơ cá nhân & Đổi mật khẩu
32. `GET /settings` (200 OK) - Tùy chọn cài đặt thông báo

### 16.2. Kết quả kiểm tra toàn diện các Nút bấm & Luồng thao tác tương tác
Mọi nút bấm tương tác đã được kiểm tra trực tiếp qua request HTTP thật, kiểm tra session redirect (302) và xác thực dữ liệu đã ghi nhận chính xác trong Database:
- **Nút Chuyển đổi giao diện Sáng/Tối (`#theme-toggle`)**: Hoạt động mượt mà ở cả client-side với toggle `data-theme` trên thẻ `<html>` và lưu trạng thái vào `localStorage` (`wm_theme`).
- **Nút Đăng nhập (`POST /login`)**: Xác thực thông tin, cấp phát session an toàn, điều hướng 302.
- **Nút Tạo công việc (`POST /tasks`)**: Nhận dữ liệu form, kiểm tra deadline/estimate, lưu task mới vào DB và cấp mã tự sinh.
- **Nút Sửa công việc (`PATCH /tasks/{id}`)**: Cập nhật tiêu đề, mô tả, ưu tiên chính xác trong DB.
- **Các nút Chuyển trạng thái quy trình làm việc (Task Workflow)**:
  - Nút "Bắt đầu làm" (`TODO` → `IN_PROGRESS`): 302 thành công, DB chuyển trạng thái.
  - Nút "Nộp duyệt" (`IN_PROGRESS` → `REVIEW`): 302 thành công, DB chuyển trạng thái.
  - Nút "Duyệt hoàn thành" (`REVIEW` → `DONE`): 302 thành công, DB chuyển trạng thái và cập nhật `completed_at`.
- **Nút Thêm bình luận (`POST /comments`)**: Lưu bình luận vào bảng `task_activities`, hỗ trợ ghi chép tiến độ.
- **Các nút Quản lý Điểm nghẽn (Blockers)**:
  - Nút "Báo điểm nghẽn" (`POST /blockers`): Ghi nhận lý do cản trở trạng thái `open`.
  - Nút "Đã xử lý xong" (`PATCH /blockers/{id}`): Ghi nhận thời điểm giải tỏa `resolved_at`.
- **Các nút Quản lý Quan hệ phụ thuộc (Dependencies)**:
  - Nút "Thêm quan hệ chờ FS" (`POST /dependencies`): Tạo liên kết Finish-to-Start kèm thời gian lag.
  - Nút "Xóa quan hệ" (`DELETE /dependencies/{id}`): Xóa an toàn không phá vỡ tính toàn vẹn DB.
- **Nút Thao tác hàng loạt (Bulk Actions `POST /tasks/bulk`)**: Cập nhật ưu tiên, trạng thái đồng loạt cho các task được chọn qua checkbox.
- **Các nút Lọc & Chip Preset trên danh sách**: Lọc tức thì theo "Tất cả", "Của tôi", "Cần duyệt", "Bị chặn", "Quá hạn" và Nút "Lưu bộ lọc" (`POST /task-filters`).
- **Các nút Bấm giờ làm việc (Live Timer)**:
  - Nút "Bắt đầu tính giờ" (`POST /timer/{taskId}`): Tạo `time_entries` với `started_at` hiện tại.
  - Nút "Dừng bấm giờ" (`POST /timers/{entryId}/stop`): Cập nhật `ended_at` và ghi nhật ký hoạt động.
- **Nút Tạo sự kiện Lịch (`POST /calendar`)**: Đặt lịch theo khung giờ, kiểm tra xung đột bận riêng tư.
- **Nút Đánh dấu đã đọc tất cả thông báo (`POST /notifications/read-all`)**: Cập nhật trạng thái `read_at` cho toàn bộ thông báo chưa đọc.
- **Nút Quét nhắc việc tự động (`POST /reminders/scan`)**: Kích hoạt bộ quét cản trở và deadline, tạo nhắc việc tức thời.
- **Các nút Báo cáo & Snapshot**:
  - Nút "Lưu snapshot bản báo cáo này" (`POST /reports`): Tạo bản chụp số liệu bất biến theo kỳ `from` - `to`.
  - Nút "Xuất CSV" (`GET /reports/{id}/export`): Tạo file CSV dữ liệu tải về chuẩn xác (200 OK).
  - Nút "Bản in" (`GET /reports/{id}/print`): Render giao diện sạch tối ưu cho máy in (200 OK).
- **Nút Lưu hồ sơ cá nhân (`PATCH /profile`)**: Cập nhật họ tên và múi giờ an toàn.
- **Nút Lưu cài đặt thông báo (`PATCH /settings`)**: Lưu tùy chọn nhận tin nhắn cho phân công, trạng thái, bình luận.

---

## 17. Nâng cấp Hệ thống Chỉnh sửa Trường dữ liệu (Field Editing UX) & Đặc tả Logic Backend (20/10/2026)

Để giải quyết triệt để nhu cầu chỉnh sửa nhanh, tiện lợi và quản lý công việc tối ưu nhất cho người dùng mà không phá vỡ kiến trúc Laravel MVC, hệ thống đã triển khai bộ công cụ tương tác chỉnh sửa hiện đại ở phía Frontend, đồng thời ghi lại các đặc tả logic cho phía Backend tiếp quản xử lý.

### 17.1. Cải tiến UI/UX đã hoàn thiện ở phía Client

1. **Bộ chọn Ưu tiên trực quan dạng nút phân đoạn (Segmented Priority Control)**:
   - Thay thế dropdown đơn điệu bằng 4 nút bấm gắn màu nhận diện thương hiệu: 🟢 Thấp (`low`), 🟡 Vừa (`medium`), 🟠 Cao (`high`), 🔴 Khẩn cấp (`urgent`).
   - Tự động đồng bộ 2 chiều với thẻ `<select name="priority">` ẩn bên dưới, bảo toàn 100% hợp đồng dữ liệu với server.

2. **Gợi ý đặt nhanh Thời lượng ước lượng (Estimate Presets & Live Formatter)**:
   - Các nút chip 1-click: `30p`, `1h`, `2h`, `4h`, `8h (1 ngày)`, `+30p`, `Xóa`.
   - Nhãn đọc hiểu trực quan thời gian thực (Live Preview Badge): tự động chuyển đổi số phút nhập thô thành dạng thân thiện, ví dụ:
     - `90 phút` → `≈ 1h 30p`
     - `480 phút` → `≈ 8h (1 ngày công)`
     - `720 phút` → `≈ 12h (1.5 ngày công)`

3. **Gợi ý chọn nhanh Hạn chót (Deadline Presets)**:
   - Các nút chip thông minh: `Hôm nay 17:30`, `Ngày mai 17:30`, `Thứ 6 tuần này`, `+7 ngày (Tuần sau)`, `Bỏ hạn`.
   - Tự động điền định dạng chuẩn `YYYY-MM-DDTHH:mm` theo đúng múi giờ `workspace.timezone`.
   - Hiệu ứng nhắc nhở viền phát sáng (`pulse-attention`) tự động focus vào ô "Lý do đổi hạn" (`deadline_reason`) nếu người dùng sửa đổi deadline, tránh gặp lỗi validation khi lưu.

4. **Thanh thao tác nổi cố định chân trang (Sticky Floating Action Bar)**:
   - Tự động hiển thị nổi phía dưới màn hình khi form dài, kèm hiệu ứng mờ kính (glassmorphism backdrop blur).
   - Người dùng không cần cuộn chuột hết trang để bấm Lưu.
   - Tích hợp phím tắt bàn phím: bấm **`Ctrl + Enter`** (hoặc `Cmd + Enter` trên macOS) ở bất kỳ đâu trong form để lưu ngay lập tức.

5. **Hộp thoại Chỉnh sửa nhanh (Quick Edit Slide-Over Modal)**:
   - Tích hợp trực tiếp trên trang chi tiết công việc `tasks/show.blade.php` qua nút **"⚡ Sửa nhanh"**.
   - Mở modal popup gọn gàng chứa các trường thiết yếu: Tiêu đề, Mức ưu tiên, Ước lượng, Hạn chót, Lý do đổi hạn, Người thực hiện, Người duyệt.
   - Gửi yêu cầu cập nhật trực tiếp qua `PATCH /tasks/{id}` và giữ người dùng ở lại trang chi tiết sau khi lưu thành công, giúp cập nhật nhiều task liên tiếp mà không mất thời gian chuyển trang.

6. **Nút Chỉnh sửa trực tiếp trên từng dòng danh sách (`tasks/index.blade.php`)**:
   - Thêm nút `✏️ Sửa` ngay cạnh tên công việc trên bảng, cho phép chuyển thẳng vào trang edit chỉ với 1 click.

7. **Bộ công cụ chọn quyền theo nhóm trong Quản lý Vai trò (`roles/_form.blade.php`)**:
   - Thêm các nút **"✓ Chọn tất cả"** và **"✕ Bỏ chọn"** cho từng nhóm module (Task, Project, Member, Workspace, Calendar, Report...).
   - Ô tìm kiếm lọc quyền tức thời (`#permission-search`): gõ từ khóa để lọc các checkbox phù hợp.

8. **Bộ nút "⊞ Mở rộng tất cả" và "⊟ Thu gọn tất cả" trên Cây công việc (`tasks/tree.blade.php`)**:
   - Cho phép người quản lý mở rộng hoặc thu gọn toàn bộ các nhóm việc WBS đa tầng chỉ trong 1 thao tác.

9. **Tự động co giãn ô nhập văn bản (Auto-expanding Textarea)**:
   - Các ô mô tả, lý do blocker, bình luận tự động điều chỉnh chiều cao theo độ dài văn bản người dùng gõ, loại bỏ thanh cuộn lồng nhau.

---

### 17.2. Ghi chú & Đặc tả Logic Phía Server (Backend Backlog cho Kỹ sư / AI Backend)

Dành cho phía Server tiếp quản để hoàn thiện các tính năng xử lý dữ liệu nâng cao hỗ trợ giao diện chỉnh sửa:

| Tính năng Server | Mục tiêu nghiệp vụ | Đặc tả Endpoint / Contract Backend | Hướng dẫn triển khai Logic |
|---|---|---|---|
| **1. Partial Inline Field Update API** | Cập nhật từng trường riêng biệt không cần gửi toàn bộ form | `PATCH /workspaces/{ws}/projects/{proj}/tasks/{task}/field` (hoặc kiểm tra Header `Accept: application/json` tại `TaskController@update`) | - Nhận payload JSON: `{ "field": "priority", "value": "high", "expected_revision": 4 }`.<br>- Validate chỉ riêng trường được yêu cầu, kiểm tra quyền `task.update` và so sánh `expected_revision`.<br>- Tăng revision +1, ghi log `task_activities` (field changed).<br>- Trả về HTTP 200 JSON: `{ "success": true, "revision": 5, "display_value": "Cao", "updated_at": "..." }`.<br>- Nếu revision không khớp, trả về HTTP 409 Conflict kèm thông tin phiên bản mới nhất. |
| **2. Auto-save Draft API** | Lưu tự động bản nháp dở dang, tránh mất dữ liệu khi rớt mạng | `POST /workspaces/{ws}/tasks/{task}/draft`<br>`GET /workspaces/{ws}/tasks/{task}/draft` | - Tạo bảng `task_drafts (task_id, user_id, payload_json, updated_at)` hoặc lưu cache Redis key `draft:task:{task_id}:user:{user_id}` với TTL 7 ngày.<br>- Client tự động gửi payload sau mỗi 10 giây khi có thay đổi.<br>- Khi người dùng mở lại trang Sửa, nếu phát hiện có draft mới hơn `task.updated_at`, server truyền biến `has_draft: true` vào view để hiển thị thông báo "Khôi phục bản nháp chưa lưu". |
| **3. Optimistic Lock Conflict Diff View** | Xử lý đối chiếu trực quan khi hai người cùng sửa một lúc | Controller ném `ValidationException` hoặc trả về view `tasks.conflict` khi `expected_revision != revision` | - Khi phát hiện xung đột phiên bản, server trả về dữ liệu 3 cột: `field`, `your_value` (dữ liệu người dùng vừa bấm lưu), và `server_value` (dữ liệu người khác đã lưu trước đó).<br>- Cho phép người dùng tick chọn giữ trường nào trước khi ghi đè bản mới nhất. |
| **4. Realtime Event Broadcasting (SSE / Reverb)** | Cập nhật trực tiếp trạng thái trên màn hình người khác | `GET /workspaces/{ws}/events` (Server-Sent Events) hoặc Laravel Reverb WebSocket channel `workspace.{id}` | - Phát các event: `TaskUpdated`, `TaskStatusChanged`, `TaskAssigned`, `TimerStarted`.<br>- Payload gửi về: `{ "event": "task.updated", "task_id": "...", "field": "status", "new_status": "DONE", "actor_name": "Nguyen Van A" }`.<br>- Phía client tự động đổi màu badge và số đếm mà người dùng không cần bấm F5 tải lại trang. |
| **5. Thùng rác & Khôi phục nhanh (Soft-delete & Recycle Bin)** | Khôi phục công việc đã lưu trữ hoặc xóa nhầm trong 30 ngày | `GET /workspaces/{ws}/trash`<br>`POST /workspaces/{ws}/tasks/{task}/restore` | - Đã có trường `archived_at` trên Task. Bổ sung bộ lọc xem nhanh thùng rác và quyền `task.restore`.<br>- Lệnh Artisan cron job tự động dọn dẹp các task đã archive quá 90 ngày nếu chủ workspace bật tùy chọn dọn rác định kỳ. |



## 18. Kết quả sửa tích hợp Backend/FE ngày 20/10/2026

Phần 16–17 là báo cáo/đề xuất từ lần làm FE trước. Không dùng câu “100% hoàn thiện” hoặc HTTP 200 của trang làm bằng chứng mọi thao tác đã hoạt động. Bản tiếp nhận thực tế có lỗi cú pháp JS, thiếu toolbar container, chip không tìm thấy input, preset dùng timezone trình duyệt và modal không giữ đầy đủ dữ liệu lỗi.

Đã sửa: Calendar handler bị thiếu dấu đóng; preset estimate/deadline dùng wrapper đúng; preset ngày do server tính theo workspace; quy tắc Thứ Sáu có hôm nay; bỏ quy đổi cứng 8h=ngày công; priority có trạng thái truy cập; Ctrl+Enter giới hạn form metadata; permission helper không chọn checkbox disabled và dùng đúng `permissions[]`; native Quick Edit có CSRF/policy/revision, giữ input, reopen khi lỗi, focus/Escape và responsive; conflict Blade 409 + native POST giải quyết từng trường, tiếp tục chặn khi bản máy chủ đổi thêm. Tiêu đề/mô tả được giữ nguyên văn và escape, chỉ enum được dịch.

Không cần thêm API `/field`, `/draft`, SSE hoặc Reverb để chạy các luồng này. Các dòng đề xuất tương ứng trong mục17.2 vẫn là backlog, không phải route đã triển khai; tính năng đối chiếu đã triển khai bằng MVC/Blade. Không có autosave bền sau khi đóng tab. Bản đối chiếu là hai giá trị mine/server, không tuyên bố có baseline để merge ba chiều.

Hợp đồng FE hiện hành ở cuối [FRONTEND_HANDOFF.md](FRONTEND_HANDOFF.md). Kết quả kiểm tra flow/dữ liệu và giới hạn cụ thể: [verification/quick-edit/README.md](verification/quick-edit/README.md).

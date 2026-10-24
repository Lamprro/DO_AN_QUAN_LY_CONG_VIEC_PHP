# Hướng dẫn sử dụng theo luồng

## 1. Chọn đúng nơi làm việc

Đăng nhập → chọn không gian làm việc ở đầu trang → mở dự án. Tên dự án hiện tại nằm trên menu; mục đang xem được đánh dấu. Không gian cá nhân có công việc riêng; dự án nhóm có quyền thành viên riêng. Nếu không thấy nút thao tác, kiểm tra quyền với người quản lý dự án.

## 2. Chuẩn bị và phân công

Tại tổng quan dự án, làm theo “Từ phân công đến hoàn thành”: quản lý thành viên → mở cây → tạo nhóm/công việc/cột mốc. Với công việc, chọn người thực hiện, người duyệt, ước lượng và hạn. Hạn được nhập/hiển thị theo múi giờ ghi trên form. GROUP dùng để gom việc; tiến độ của nhóm tự tổng hợp, không chuyển trạng thái thủ công.

## 3. Tìm việc cần làm

- “Việc của tôi” lấy công việc được giao cho tài khoản hiện tại.
- “Chờ tôi duyệt” lấy việc REVIEW mà tài khoản có quyền duyệt.
- “Đang bị chặn” lấy việc có blocker đang mở; dependency là điều kiện riêng xem ở chi tiết.
- “Quá hạn” lấy WORK chưa đóng có hạn đã qua.
- Nhập tiêu đề/mã, chọn trạng thái rồi “Áp dụng bộ lọc”. Mở “Lọc thêm” để chọn người làm, ưu tiên, lưu trữ và sắp xếp.

Preset nhanh đặt lại các điều kiện trước đó. Danh sách ↔ Kanban giữ bộ lọc; “Xóa bộ lọc” giữ cách xem. Cây hiển thị cấu trúc đầy đủ trong phạm vi được xem, không giữ điều kiện tìm kiếm. Kanban không hiển thị GROUP và phân trang 10 thẻ mỗi cột.

## 4. Cập nhật thông tin

Mở chi tiết → “Sửa nhanh” để đổi tiêu đề, ưu tiên, ước lượng, hạn hoặc phân công theo quyền. Chọn preset thời lượng/hạn nếu phù hợp; đổi hạn phải có lý do. Bấm Lưu hoặc Ctrl+Enter. Chỉ flash thành công và dữ liệu tải lại mới xác nhận đã lưu.

“Đóng (chưa lưu)” giữ bản nhập trong trang hiện tại, không ghi DB; rời/tải lại trang có thể mất bản chưa lưu. Dùng “Chỉnh sửa đầy đủ” cho trường khác. Không có JavaScript vẫn dùng form chỉnh sửa đầy đủ.

Nếu có người khác vừa sửa, màn hình đối chiếu xuất hiện: đọc dữ liệu hiện tại và bản của bạn, chọn theo trường rồi gửi. Không bỏ `expected_revision`, không dùng nút Back rồi gửi liên tục để ép ghi đè.

Muốn đổi hàng loạt: ở danh sách tích các dòng → công cụ hàng loạt tự mở → chọn thao tác và gửi. Tối đa 50 việc; một lỗi quyền/phiên bản khiến cả nhóm không lưu. Đọc lỗi, tải lại dữ liệu và chọn lại.

## 5. Thực hiện → nộp duyệt → hoàn thành

Đọc “Bước tiếp theo của công việc”, chọn “Đến phần xử lý trạng thái”. Nếu bị chặn, xử lý blocker, phụ thuộc hoặc timer theo lý do hiển thị trước khi thao tác.

1. Người làm/người có quyền bắt đầu công việc.
2. Khi đang thực hiện, ghi giờ nếu cần; bình luận và thêm tệp làm bằng chứng. Báo blocker khi gặp trở ngại.
3. Dừng timer trước khi nộp duyệt. Hệ thống kiểm tra người duyệt và các ràng buộc.
4. Người duyệt chấp thuận để hoàn thành hoặc yêu cầu sửa và ghi lý do. Người làm tiếp tục sửa rồi nộp lại.
5. Hủy/mở lại phải có lý do; dependency có thể ngăn mở lại nếu việc kế tiếp đã bắt đầu.

Stepper cho biết trạng thái hiện tại, không phải checklist lịch sử. Lịch sử bên dưới lưu các chuyển trạng thái và lý do. Dùng liên kết “Vấn đề đang chặn”, “Phụ thuộc”, “Trao đổi và tệp”, “Lịch sử”, “Giờ thực tế” để tới đúng phần nhanh hơn.

## 6. Theo dõi và kết thúc

Mở Lịch/Timeline để xem khung thời gian; Dashboard/báo cáo để xem số liệu theo quyền. Lưu trữ khi cần giữ lịch sử nhưng ngừng chỉnh sửa; xóa vĩnh viễn chỉ dành cho dữ liệu nháp thỏa điều kiện. Không dùng xóa thay cho hoàn thành/lưu trữ.

## 7. Trên điện thoại

Bấm Menu → chọn mục cần xem, hoặc “Đóng điều hướng” để trở lại. Các bộ lọc và form xếp dọc. Kanban cuộn ngang trong vùng cột. Có thể sửa nhanh bằng modal, lưu form và chuyển trạng thái giống máy tính. Nút chuyển trạng thái bị vô hiệu có lý do hiển thị; không cần đoán nguyên nhân.

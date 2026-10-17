# Hướng dẫn triển khai dự án quản lý công việc

## Chỉ đạo của chủ dự án

- Dùng Laravel MVC với Model/Eloquent, Controller và Blade. Backend xử lý logic và database; backend chuẩn bị sẵn Blade/form/action chạy thật; AI frontend tiếp tục thiết kế giao diện theo docs/FRONTEND_HANDOFF.md. Không tự đổi sang REST API/SPA.
- Chỉ đạo mới: hoàn thiện toàn bộ phần còn lại, kiểm tra bằng dữ liệu thật; không dừng duyệt từng phần. Giữ nhật ký và giải thích flow khi bàn giao.
- Các key và tích hợp bên ngoài được cấu hình sau. Giữ tích hợp tắt khi chưa có cấu hình thật; dữ liệu mô phỏng phải được ghi nhãn rõ.
- **Không thêm các lớp kiểm thử để thay cho xác minh thực tế.** Kiểm tra trực tiếp bằng flow thật trên route/controller/service/database đã có, lưu lại thao tác, dữ liệu trước/sau, lỗi và kết quả đọc lại. Không mock database hay bịa endpoint/flow để báo hoàn thành. Ở phần nền chưa có HTTP, kiểm tra Artisan/Eloquent/SQL và ghi rõ giới hạn này.
- Không sử dụng kết quả các test mặc định Laravel để chứng minh nghiệp vụ hoạt động. Không chạy test suite thay cho luồng thực tế nếu chủ dự án chưa thay đổi yêu cầu trên.
- Không chạy migrate:fresh/rollback hoặc xóa dữ liệu đang dùng để phục vụ kiểm tra. Dùng database review/lifecycle riêng; bảo toàn dữ liệu và APP_KEY cũ khi đã có thông tin mã hóa.
- Không sửa/xóa báo cáo hoặc commit từ chat khác. Đồng bộ và đọc trạng thái Git trước khi triển khai.

## Tài liệu chuẩn

- docs/IMPLEMENTATION_PROGRESS.md: trạng thái thực tế từng phần và điểm chờ duyệt.
- docs/STAGE_01_DATABASE.md: hàm và luồng đã triển khai ở phần 1.
- docs/verification/README.md: minh chứng kiểm tra trực tiếp và giới hạn.
- docs/FRONTEND_HANDOFF.md: hướng dẫn AI frontend; route dự kiến phải phân biệt với route đã có thật.
- docs/design/schema-v3.json và AI_Work_Management_ERD_V3.drawio: schema V3.
- docs/planning/KE_HOACH_TRIEN_KHAI_LARAVEL_MVC_V3.md: kế hoạch tổng thể; khi nội dung về cách kiểm thử khác với chỉ đạo mới ở trên thì ưu tiên chỉ đạo mới của chủ dự án.

Chủ dự án đã cho phép triển khai toàn bộ phần còn lại trong lượt tiếp theo phần 6.

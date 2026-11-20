# Sửa task chưa hoàn thành và thêm việc con — 16/11/2026

Quyết định cuối của chủ dự án: DONE khóa sửa nội dung và thêm con. TODO, IN_PROGRESS, REVIEW, CANCELLED được sửa theo quyền. Vẫn giữ bảo vệ phạm vi/archived, revision và lịch sử. Luồng chuyển trạng thái/duyệt/mở lại hiện hành không bị thay bằng sửa trạng thái tùy ý trong form.

Trang chi tiết có “Thêm công việc con”, mở form đúng workspace/project gốc và chọn sẵn cha. WORK/MILESTONE có thể có con mà không đổi loại hoặc xóa phân công, estimate, timer, lịch sử của việc cha. Trạng thái WORK/MILESTONE giữ riêng; GROUP vẫn tổng hợp từ con. Giới hạn tối đa ba cấp, cùng nguồn và chống vòng lặp giữ nguyên. Kiểm quyền tạo và sửa cha được thực hiện lại dưới khóa khi ghi. Cha đã DONE không nhận con mới; con đã tồn tại vẫn được sửa nếu chính nó chưa DONE. Đổi loại vẫn giữ các ràng buộc dữ liệu nghiệp vụ.

Kiểm chứng trên SQLite review riêng: 32 kiểm tra service/Policy/Blade đạt; 21 HTTP thật và 5 đối chiếu kết quả đạt. Bao gồm sửa IN_PROGRESS/REVIEW/CANCELLED, tạo con, chọn sẵn cha, DONE trả 403, revision cũ, vòng lặp, cấp bốn và người ngoài quyền. Service fixture chạy trong transaction hoàn tác; HTTP tạo dữ liệu chỉ trong DB review. Blade biên dịch đạt. Chưa kiểm tra trực quan trên trình duyệt hoặc cạnh tranh DB production.

Các thay đổi local có trước của chủ dự án được giữ nguyên. Bản vá được lấy từ snapshot trước lượt này để tách khỏi phần việc đang dở.

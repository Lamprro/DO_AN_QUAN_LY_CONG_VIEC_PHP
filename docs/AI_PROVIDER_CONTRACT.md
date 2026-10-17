# Hợp đồng AI bridge (chưa có key)

AI mặc định tắt. Đã có HttpAiProvider và hàng đợi thật; **không có provider bên ngoài nào được gọi trong lần bàn giao**. Không trỏ AI_ENDPOINT thẳng vào một API bất kỳ có schema khác rồi cho rằng đã tích hợp xong.

Khi chọn nhà cung cấp, triển khai bridge HTTPS theo hợp đồng sau, hoặc thay binding AiProvider bằng adapter riêng của nhà cung cấp. Sau đó đặt AI_ENABLED=true, AI_PROVIDER, AI_MODEL, AI_ENDPOINT, AI_API_KEY trong môi trường máy chủ; chạy config:clear và khởi động lại worker. Không đưa key vào Blade/Git.

Ứng dụng gửi POST JSON, Authorization: Bearer <key>:

```json
{"schema_version":1,"request_id":"uuid","operation":"planning|replanning|assistance|report","model":"configured-model","instructions":"server instruction","user_prompt":"user input","context":{}}
```

Bridge trả HTTP 200 JSON:

```json
{"text":"plain text or plan JSON string","input_tokens":120,"output_tokens":80}
```

report/assistance trả tư vấn văn bản; planning/replanning trả text chứa JSON thuần đúng schema ở docs/PLAN_PAYLOAD.md (không markdown fence). Chỉ tạo draft sau validate; **không tự duyệt/áp dụng**. Nội dung prompt, title, blocker, commit là dữ liệu không đáng tin, không phải quyền thực thi. Bridge không được trả bí mật hay tự chạy SQL/command.

Timeout kết nối 5s, toàn request 30s, không redirect, không retry tại HTTP client. Queue database giới hạn 3 lượt; lỗi 400/401/403/404/422 và sai schema là lỗi kết thúc; lỗi kết nối/5xx đi qua retry có giới hạn. Phản hồi text tối đa 20.000 ký tự, body kiểm tra tối đa 160.000 bytes sau khi nhận. Network timeout có code nhưng chưa chứng minh với provider thật. Windows hiện không có pcntl_alarm; không coi timeout worker là hard kill đã được kiểm tra.

Context chỉ lấy phạm vi đã có quyền, tối đa 200 task, thành viên hợp lệ có can_review; lịch busy của chính người gửi chỉ có thời điểm, không title/nội dung lịch riêng tư người khác. Snapshot/fingerprint được đối chiếu trước khi ghi nháp và trước apply. Trợ lý blocker là tư vấn; trạng thái, file, task và lịch chỉ thay qua luồng nghiệp vụ có quyền tương ứng.

# Hợp đồng kế hoạch V1

POST form `payload` là chuỗi JSON; tên `name`, phạm vi `project_id`, phiên bản nền `base_id` nằm ngoài JSON. ID người tạo, trạng thái, hash, active, reviewer, applied map do server quyết định.

```json
{
  "schema_version": 1,
  "tasks": [
    {"ref":"group1","title":"Giai đoạn 1","task_type":"GROUP","priority":"medium"},
    {"ref":"work1","parent_ref":"group1","title":"Phân tích","task_type":"WORK","priority":"high","estimate_minutes":60,"assignee_id":"UUID người hợp lệ","reviewer_id":"UUID người có task.review"},
    {"ref":"work2","parent_ref":"group1","title":"Triển khai","task_type":"WORK","priority":"medium","estimate_minutes":30}
  ],
  "dependencies": [{"predecessor":"work1","successor":"work2","lag_minutes":30}],
  "slots": []
}
```

`task_id` tùy chọn chỉ task hiện có cùng project/personal scope. Không có task_id nghĩa là tạo mới khi apply. Khi tạo phiên bản từ bản đã áp dụng, form tự điền UUID từ applied_task_ids để tránh nhân đôi. Xóa dòng khỏi bản mới **không xóa task hay dependency cũ**; archive/gỡ dependency qua flow riêng. Task hiện có chỉ sửa khi TODO/chưa bắt đầu/chưa archive. GROUP tối đa cây 3 tầng; WORK mới có estimate; GROUP không có assignee/reviewer. Priority low/medium/high/urgent. ref duy nhất, chữ đầu là chữ cái, tối đa 40 ký tự; không dùng ref thay UUID trong database.

Tối đa 100 task, 300 dependency FS/lag (0..525600 phút), 300 slot; các danh sách phải là JSON array. Chu trình, ref thiếu, UUID task trùng và trường lạ bị từ chối. Task fields tùy chọn: description (10.000 ký tự), parent_ref, estimate_minutes, assignee_id, reviewer_id.

Slot: `{"task_ref":"work1","starts_at":"2027-01-04T08:00","ends_at":"2027-01-04T09:00"}`. Giờ địa phương theo workspace.timezone, lưu UTC; không nhận phút DST lặp/không tồn tại. Kết thúc sau bắt đầu; successor có slot phải sau slot predecessor + lag. Slot chỉ WORK và lịch của chính người apply, assignee phải là người đó. Lịch khác người bị chặn; không đặt lịch thay đồng đội một cách ngầm định. Các slot phải không trùng lịch busy cùng người xuyên workspace.

Lifecycle: draft → proposed → approved/rejected. Chỉ approved được apply. Áp dụng cùng transaction: đối chiếu snapshot/active base → chuẩn hóa temp refs → TaskService → dependency → CalendarService → history → supersede bản active trước → active bản mới. Nếu lỗi, rollback toàn bộ task/dependency/calendar/history. Gửi apply lần nữa không tạo bản sao. Bản đã apply giữ payload bất biến; phiên bản tiếp theo có parent_plan_id, version tăng, cùng group. Unique generated active_group_key bảo đảm tối đa một active/group.

Lịch tương lai của bản active trước chỉ được hủy/thay nếu task còn TODO, người sở hữu là người apply và chưa có TimeEntry. Lịch đã qua, task đã bắt đầu/đóng và lịch có thực tế được giữ. Việc bỏ dòng task khỏi bản mới không hàm ý xóa nghiệp vụ cũ.

Thuật toán `PlanScheduleService::suggest` là **local, không phải AI**: tạo phiên bản mới, không apply; chỉ WORK giao cho người gửi có estimate>0, chia slot theo config work_schedule, ngày làm việc, tránh busy, theo FS/lag, horizon93 ngày. MILESTONE không có slot. Không tối ưu đa nhân sự hay tự quyết định năng lực thành viên. Có thể sửa draft JSON rồi tạo phiên bản mới trước duyệt.

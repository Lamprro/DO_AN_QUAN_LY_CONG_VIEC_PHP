<?php

return [
    'uploaded' => 'Không tải được tệp; kiểm tra dung lượng và cấu hình upload của PHP.', 'mimes' => ':Attribute không đúng loại tệp cho phép.', 'extensions' => ':Attribute không đúng phần mở rộng cho phép.', 'file' => ':Attribute phải là tệp hợp lệ.',
    'integer' => ':Attribute phải là số nguyên.', 'date_format' => ':Attribute không đúng định dạng :format.', 'regex' => ':Attribute không đúng định dạng.', 'prohibited' => 'Không được gửi trường :attribute trong thao tác này.', 'enum' => ':Attribute không hợp lệ.',
    'required' => 'Vui lòng nhập :attribute.', 'string' => ':Attribute phải là chuỗi.', 'email' => 'Email không đúng định dạng.',
    'unique' => ':Attribute đã được sử dụng.', 'confirmed' => 'Xác nhận :attribute không khớp.', 'array' => ':Attribute phải là danh sách.',
    'exists' => ':Attribute không hợp lệ trong phạm vi này.', 'in' => ':Attribute không hợp lệ.', 'uuid' => ':Attribute không phải mã UUID hợp lệ.',
    'timezone' => 'Múi giờ không hợp lệ.', 'distinct' => ':Attribute bị trùng.', 'boolean' => ':Attribute phải đúng/sai.',
    'current_password' => 'Mật khẩu hiện tại không đúng.',
    'max' => ['file' => ':Attribute tối đa :max KB.', 'string' => ':Attribute tối đa :max ký tự.', 'array' => ':Attribute tối đa :max phần tử.', 'numeric' => ':Attribute không được lớn hơn :max.'],
    'min' => ['string' => ':Attribute cần ít nhất :min ký tự.', 'numeric' => ':Attribute không được nhỏ hơn :min.'],
    'password' => ['letters' => 'Mật khẩu phải có chữ cái.', 'numbers' => 'Mật khẩu phải có chữ số.'],
    'attributes' => ['file' => 'tệp đính kèm', 'task_type' => 'loại công việc', 'parent_task_id' => 'nhóm cha', 'group_label' => 'nhãn nhóm', 'sort_order' => 'thứ tự', 'expected_status' => 'trạng thái hiện tại', 'predecessor_task_id' => 'công việc trước', 'lag_minutes' => 'thời gian chờ', 'content' => 'nội dung', 'reason' => 'lý do', 'code' => 'mã task', 'title' => 'tiêu đề', 'description' => 'mô tả', 'estimate_minutes' => 'ước lượng thời gian', 'deadline_at' => 'hạn hoàn thành', 'assignee_id' => 'người thực hiện', 'reviewer_id' => 'người duyệt', 'parent_project_id' => 'project cha', 'workspace_member_id' => 'thành viên workspace', 'priority' => 'ưu tiên', 'name' => 'tên', 'email' => 'email', 'password' => 'mật khẩu', 'current_password' => 'mật khẩu hiện tại', 'timezone' => 'múi giờ', 'role_id' => 'vai trò', 'member_id' => 'thành viên', 'permissions' => 'quyền', 'status' => 'trạng thái'],
];

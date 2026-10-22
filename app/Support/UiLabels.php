<?php

namespace App\Support;

class UiLabels
{
    public static function text(mixed $value): string
    {
        $value = $value instanceof \BackedEnum ? $value->value : (string) $value;

        return ['TASK_BLOCK' => 'Khung công việc', 'MEETING' => 'Cuộc họp', 'BUSY' => 'Bận', 'NOTE' => 'Ghi chú', 'EXTRA_AVAILABLE' => 'Rảnh bổ sung', 'planning' => 'Lập kế hoạch', 'replanning' => 'Điều chỉnh kế hoạch', 'assistance' => 'Hỗ trợ blocker', 'report' => 'Nhận xét báo cáo', 'disabled' => 'Đã tắt', 'pending' => 'Chờ xử lý', 'processed' => 'Đã xử lý', 'ignored' => 'Bỏ qua', 'TODO' => 'Chưa bắt đầu', 'IN_PROGRESS' => 'Đang thực hiện', 'REVIEW' => 'Chờ duyệt', 'DONE' => 'Hoàn thành', 'CANCELLED' => 'Đã hủy', 'GROUP' => 'Nhóm việc', 'WORK' => 'Công việc', 'MILESTONE' => 'Mốc',
            'Owner' => 'Chủ sở hữu', 'Manager' => 'Quản lý', 'Member' => 'Thành viên', 'Viewer' => 'Chỉ xem', 'suspended' => 'Tạm dừng', 'left' => 'Đã rời', 'low' => 'Thấp', 'medium' => 'Vừa', 'high' => 'Cao', 'urgent' => 'Khẩn cấp', 'draft' => 'Bản nháp', 'active' => 'Đang hoạt động', 'on_hold' => 'Tạm hoãn', 'completed' => 'Hoàn tất', 'cancelled' => 'Đã hủy', 'proposed' => 'Chờ duyệt', 'approved' => 'Đã duyệt', 'rejected' => 'Bị từ chối', 'superseded' => 'Đã được thay thế', 'queued' => 'Đang chờ', 'running' => 'Đang xử lý', 'succeeded' => 'Thành công', 'failed' => 'Thất bại', 'personal' => 'Cá nhân', 'team' => 'Nhóm', 'planned' => 'Dự kiến', 'open' => 'Đang mở', 'resolved' => 'Đã xử lý'][$value] ?? $value;
    }
}

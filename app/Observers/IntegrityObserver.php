<?php

namespace App\Observers;

use App\Enums\ActivityType;
use App\Models\CalendarProposal;
use App\Models\Plan;
use App\Models\ReportSnapshot;
use App\Models\TaskActivity;
use App\Models\TaskTemplate;
use App\Models\TaskTemplateRun;
use App\Support\ReferenceIntegrity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class IntegrityObserver
{
    public function saving(Model $model): void
    {
        app(ReferenceIntegrity::class)->validate($model);
    }

    public function updating(Model $model): void
    {
        if ($model instanceof TaskTemplateRun) {
            throw ValidationException::withMessages(['template' => 'Lịch sử tạo việc là bất biến.']);
        }
        if ($model instanceof TaskTemplate && ($model->isDirty('definition') || $model->isDirty('created_by'))) {
            throw ValidationException::withMessages(['template' => 'Nội dung mẫu và người tạo giữ nguyên; tạo mẫu mới khi muốn thay bộ công việc.']);
        }
        if ($model instanceof CalendarProposal && array_diff(array_keys($model->getDirty()), ['status', 'revision', 'responded_at', 'calendar_event_id', 'updated_at'])) {
            throw ValidationException::withMessages(['proposal' => 'Nội dung đề xuất giữ nguyên; thu hồi và gửi đề xuất mới nếu cần đổi giờ.']);
        }

        if ($model instanceof Plan && $model->getRawOriginal('applied_at')) {
            if (array_diff(array_keys($model->getDirty()), ['status', 'is_active'])) {
                throw ValidationException::withMessages(['plan' => 'Kế hoạch đã áp dụng là bất biến; tạo phiên bản mới.']);
            }
            if ($model->status->value !== 'superseded' || $model->is_active) {
                throw ValidationException::withMessages(['plan' => 'Chỉ được đánh dấu thay thế bản đã áp dụng.']);
            }
        }
        if ($model instanceof ReportSnapshot) {
            throw ValidationException::withMessages(['report' => 'Bản báo cáo đã lưu là bất biến; hãy tạo bản mới.']);
        }
        if ($model instanceof TaskActivity && $model->getRawOriginal('type') === ActivityType::HISTORY->value) {
            throw ValidationException::withMessages(['type' => 'Lịch sử hệ thống không được chỉnh sửa.']);
        }
    }

    public function deleting(Model $model): void
    {
        if ($model instanceof TaskTemplateRun || $model instanceof TaskTemplate || $model instanceof CalendarProposal) {
            throw ValidationException::withMessages(['history' => 'Giữ lịch sử tạo việc và đề xuất lịch; dùng hủy hoặc lưu trữ.']);
        }

        if ($model instanceof Plan) {
            throw ValidationException::withMessages(['plan' => 'Giữ lịch sử phiên bản kế hoạch.']);
        }
        if ($model instanceof ReportSnapshot) {
            throw ValidationException::withMessages(['report' => 'Giữ nguyên bản báo cáo đã lưu.']);
        }
        if ($model instanceof TaskActivity && $model->type === ActivityType::HISTORY) {
            throw ValidationException::withMessages(['type' => 'Lịch sử hệ thống không được xóa.']);
        }
    }
}

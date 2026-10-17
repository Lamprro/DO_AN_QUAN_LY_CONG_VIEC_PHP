<?php

namespace App\Observers;

use App\Enums\ActivityType;
use App\Models\Plan;
use App\Models\ReportSnapshot;
use App\Models\TaskActivity;
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

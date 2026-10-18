<?php

namespace App\Jobs;

use App\Models\TaskReminder;
use App\Services\TaskReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendTaskReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $reminderId)
    {
        $this->onConnection('database')->onQueue('work-management-reminders')->beforeCommit();
    }

    public function handle(TaskReminderService $service): void
    {
        $service->deliver($this->reminderId);
    }

    public function failed(?\Throwable $error): void
    {
        TaskReminder::whereKey($this->reminderId)->where('status', 'queued')->update(['status' => 'failed', 'skip_reason' => 'Job hết lượt xử lý; chưa có xác nhận gửi. Kiểm tra vận hành.']);
    }
}

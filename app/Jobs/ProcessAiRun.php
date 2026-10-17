<?php

namespace App\Jobs;

use App\Contracts\AiProvider;
use App\Exceptions\AiProviderUnavailable;
use App\Services\AiRunService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessAiRun implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 45;

    public bool $failOnTimeout = true;

    public function __construct(public string $runId)
    {
        $this->onConnection('database')->onQueue(config('ai.queue'))->beforeCommit();
    }

    public function backoff(): array
    {
        return [5, 20];
    }

    public function handle(AiProvider $provider, AiRunService $service): void
    {
        $claimed = $service->begin($this->runId, $this->job?->uuid());
        if (! $claimed) {
            return;
        }[$run,$call] = $claimed;
        try {
            if (! config('work_management.ai_enabled')) {
                throw new AiProviderUnavailable('AI đang tắt; chưa gọi nhà cung cấp và chưa tạo nhận xét AI.');
            }
            $result = $provider->complete($run);
            if (trim($result->text) === '' || mb_strlen($result->text) > 20000 || ($result->inputTokens !== null && $result->inputTokens < 0) || ($result->outputTokens !== null && $result->outputTokens < 0)) {
                throw new AiProviderUnavailable('Phản hồi không đúng hợp đồng nhận xét AI.');
            }
            $service->finish($run, $call, $result);
        } catch (AiProviderUnavailable $error) {
            // Terminal configuration/schema error: acknowledge the queue job, keep a clear domain error.
            $service->finish($run, $call, null, $error->getMessage());
        } catch (\Throwable) {
            $retry = $call->attempt_no < config('ai.max_attempts') && $this->attempts() < $this->tries;
            $service->finish($run, $call, null, 'Lỗi xử lý nhà cung cấp; không lưu nội dung lỗi có thể chứa thông tin bí mật.', $retry);
            throw new \RuntimeException('AI provider processing failed.');
        }
    }

    public function failed(?\Throwable $error): void
    {
        app(AiRunService::class)->abandoned($this->runId, $this->job?->uuid());
    }
}

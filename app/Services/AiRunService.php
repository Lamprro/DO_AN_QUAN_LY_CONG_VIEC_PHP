<?php

namespace App\Services;

use App\Data\AiProviderResult;
use App\Exceptions\AiProviderUnavailable;
use App\Jobs\ProcessAiRun;
use App\Models\AiCall;
use App\Models\AiRun;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Support\ReportAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

class AiRunService
{
    public function __construct(private ReportService $reports, private ReportAccess $access) {}

    public function requestPlanning(User $actor, Workspace $workspace, array $data): AiRun
    {
        return DB::transaction(function () use ($actor, $workspace, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $project = empty($data['project_id']) ? null : $workspace->projects()->findOrFail($data['project_id']);
            abort_unless(app(PlanService::class)->allowed($actor, $workspace, $project, 'plan.create'), 403);
            $base = empty($data['base_id']) ? null : Plan::findOrFail($data['base_id']);
            if ($data['operation'] === 'replanning' && ! $base) {
                throw ValidationException::withMessages(['base_id' => 'Điều chỉnh cần phiên bản nền.']);
            }
            if ($base) {
                abort_unless(($base->data['schema_version'] ?? null) === 1 && $base->workspace_id === $workspace->id && $base->project_id === $project?->id && app(PlanService::class)->allowed($actor, $workspace, $project, 'plan.update'), 403);
            }
            $key = hash('sha256', $actor->id.':'.$workspace->id.':'.$data['idempotency_token']);
            $digest = hash('sha256', json_encode(array_intersect_key($data, array_flip(['project_id', 'base_id', 'operation', 'user_prompt']))));
            if ($existing = AiRun::where('idempotency_key', $key)->first()) {
                Gate::forUser($actor)->authorize('view', $existing);
                if (($existing->context_snapshot['request_digest'] ?? null) !== $digest) {
                    throw ValidationException::withMessages(['idempotency_token' => 'Mã gửi đã dùng cho nội dung khác.']);
                }

                return $existing;
            }
            $context = app(PlanningContext::class)->capture($actor, $workspace, $project, $base);
            $context['request_digest'] = $digest;
            $run = AiRun::create(['workspace_id' => $workspace->id, 'project_id' => $project?->id, 'requested_by' => $actor->id, 'operation' => $data['operation'], 'trigger_type' => 'manual', 'user_prompt' => $data['user_prompt'], 'context_snapshot' => $context, 'status' => 'queued', 'idempotency_key' => $key]);
            $this->enqueue($run);

            return $run;
        }, 3);
    }

    private function canProcess(AiRun $run): bool
    {
        if (! $run->requester) {
            return false;
        }

        return $run->operation->value === 'report'
            ? $this->access->manifest($run->requester, $run->workspace, $run->context_snapshot['metrics'] ?? [], 'report.create')
            : app(PlanningContext::class)->allowed($run->requester, $run, 'plan.create');
    }

    private function enqueue(AiRun $run): void
    {
        // Job and run use the SAME database transaction. No sync/deferred/external connection fallback.
        $default = config('database.default');
        $queueDb = config('queue.connections.database.connection') ?? $default;
        if ($queueDb !== $default || config('queue.connections.database.driver') !== 'database') {
            throw ValidationException::withMessages(['queue' => 'Hàng đợi phải dùng cùng database để lưu yêu cầu nguyên tử.']);
        }
        Queue::connection('database')->pushOn(config('ai.queue'), new ProcessAiRun($run->id));
    }

    public function request(User $actor, Workspace $workspace, array $data): AiRun
    {
        return DB::transaction(function () use ($actor, $workspace, $data) {
            Workspace::lockForUpdate()->findOrFail($workspace->id);
            $this->reports->filters($actor, $workspace, $data, 'report.create');
            $key = hash('sha256', $actor->id.':'.$workspace->id.':'.$data['idempotency_token']);
            $request = ['from' => $data['from'], 'to' => $data['to'], 'project_id' => $data['project_id'] ?? null, 'subject_user_id' => $data['subject_user_id'] ?? null, 'include_archived' => (bool) ($data['include_archived'] ?? false), 'timezone' => $actor->timezone, 'user_prompt' => $data['user_prompt']];
            $digest = hash('sha256', json_encode($request));
            if ($existing = AiRun::where('idempotency_key', $key)->first()) {
                Gate::forUser($actor)->authorize('view', $existing);
                if (($existing->context_snapshot['request_digest'] ?? null) !== $digest) {
                    throw ValidationException::withMessages(['idempotency_token' => 'Mã gửi đã dùng cho nội dung khác. Mở form mới để tạo yêu cầu khác.']);
                }

                return $existing;
            }
            $metrics = $this->reports->metrics($actor, $workspace, $data, 'report.create');
            $run = AiRun::create(['workspace_id' => $workspace->id, 'project_id' => $metrics['filters']['project_id'], 'requested_by' => $actor->id, 'operation' => 'report', 'trigger_type' => 'manual',
                'user_prompt' => $data['user_prompt'], 'context_snapshot' => ['schema_version' => 1, 'request_digest' => $digest, 'metrics' => $metrics, 'work_schedule' => config('work_schedule'), 'mode' => 'report_commentary_only'],
                'status' => 'queued', 'idempotency_key' => $key]);
            $this->enqueue($run);

            return $run;
        }, 3);
    }

    public function action(User $actor, AiRun $run, string $action, string $expected): void
    {
        DB::transaction(function () use ($actor, $run, $action, $expected) {
            $run = AiRun::lockForUpdate()->findOrFail($run->id);
            Gate::forUser($actor)->authorize('manage', $run);
            if ($run->status->value !== $expected) {
                throw ValidationException::withMessages(['status' => 'Trạng thái đã đổi; tải lại yêu cầu.']);
            }
            if ($action === 'cancel') {
                if (! in_array($run->status->value, ['queued', 'running'], true)) {
                    throw ValidationException::withMessages(['status' => 'Chỉ hủy yêu cầu đang chờ hoặc xử lý.']);
                }
                $run->update(['status' => 'cancelled', 'finished_at' => now(), 'error_message' => null]);

                return;
            }
            if ($run->status->value !== 'failed' || $run->calls()->count() >= config('ai.max_attempts')) {
                throw ValidationException::withMessages(['status' => 'Chỉ thử lại yêu cầu lỗi còn lượt xử lý (tối đa 3 lượt).']);
            }
            $run->update(['status' => 'queued', 'started_at' => null, 'finished_at' => null, 'error_message' => null]);
            $this->enqueue($run);
        }, 3);
    }

    public function begin(string $id, ?string $jobUuid = null): ?array
    {
        return DB::transaction(function () use ($id, $jobUuid) {
            $run = AiRun::lockForUpdate()->find($id);
            if (! $run || $run->status->value !== 'queued') {
                return null;
            }
            $actor = $run->requester;
            if (! $this->canProcess($run)) {
                $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Không còn quyền xử lý phạm vi dữ liệu của yêu cầu.']);

                return null;
            }
            $attempt = (int) $run->calls()->max('attempt_no') + 1;
            if ($attempt > config('ai.max_attempts')) {
                $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Đã hết số lượt xử lý.']);

                return null;
            }
            $run->update(['status' => 'running', 'started_at' => now(), 'finished_at' => null, 'error_message' => null]);
            $call = AiCall::create(['ai_run_id' => $run->id, 'step_key' => $run->operation->value, 'attempt_no' => $attempt, 'provider' => config('ai.provider'), 'model' => config('ai.model'),
                'request_payload' => ['job_uuid' => $jobUuid, 'context_sha256' => hash('sha256', json_encode($run->context_snapshot)), 'mode' => $run->operation->value, 'timeout_seconds' => config('ai.timeout_seconds')], 'status' => 'running', 'started_at' => now()]);

            return [$run, $call];
        }, 3);
    }

    public function finish(AiRun $run, AiCall $call, ?AiProviderResult $result, ?string $error = null, bool $retry = false): void
    {
        DB::transaction(function () use ($run, $call, $result, $error, $retry) {
            $run = AiRun::lockForUpdate()->findOrFail($run->id);
            $call = AiCall::lockForUpdate()->findOrFail($call->id);
            if ($call->status->value !== 'running') {
                return;
            }
            if ($run->status->value !== 'running' || $run->calls()->orderByDesc('attempt_no')->value('id') !== $call->id) {
                $call->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Kết quả không được áp dụng vì yêu cầu đã hủy hoặc kết thúc.']);

                return;
            }
            // Recheck access after provider returns; no revoked data/result may be persisted as success.
            if (! $this->canProcess($run)) {
                $result = null;
                $error = 'Không còn quyền xử lý phạm vi dữ liệu.';
                $retry = false;
            }
            if ($result && in_array($run->operation->value, ['planning', 'replanning'], true)) {
                try {
                    app(PlanningContext::class)->result($run, $result->text);
                } catch (AiProviderUnavailable $e) {
                    $result = null;
                    $error = $e->getMessage();
                    $retry = false;
                }
            }
            $call->update(['status' => $result ? 'succeeded' : 'failed', 'finished_at' => now(), 'error_message' => $error,
                'response_payload' => $result ? ['text' => $result->text] : null, 'input_tokens' => $result?->inputTokens, 'output_tokens' => $result?->outputTokens]);
            $run->update(['status' => $result ? 'succeeded' : ($retry ? 'queued' : 'failed'), 'finished_at' => $retry ? null : now(), 'error_message' => $error]);
        }, 3);
    }

    public function abandoned(string $id, ?string $jobUuid = null, bool $staleOnly = false): bool
    {
        return DB::transaction(function () use ($id, $jobUuid, $staleOnly) {
            $run = AiRun::lockForUpdate()->find($id);
            if (! $run || ! in_array($run->status->value, ['running', 'queued'], true)) {
                return false;
            }
            if ($staleOnly && ($run->status->value !== 'running' || ! $run->started_at || $run->started_at->gte(now()->subSeconds(config('ai.lease_seconds'))))) {
                return false;
            }
            $latest = $run->calls()->orderByDesc('attempt_no')->first();
            if ($jobUuid && ($latest?->request_payload['job_uuid'] ?? null) !== $jobUuid) {
                return false;
            }
            $run->calls()->where('status', 'running')->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Worker dừng hoặc hết lượt xử lý.']);
            $run->update(['status' => 'failed', 'finished_at' => now(), 'error_message' => 'Worker dừng hoặc hết lượt xử lý. Có thể thử lại nếu còn lượt.']);

            return true;
        }, 3);
    }

    public function recoverStale(): int
    {
        $ids = AiRun::where('status', 'running')->where('started_at', '<', now()->subSeconds(config('ai.lease_seconds')))->pluck('id');
        $count = 0;
        foreach ($ids as $id) {
            if ($this->abandoned($id, null, true)) {
                $count++;
            }
        }

        return $count;
    }
}

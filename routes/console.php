<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\AiRunService;
use App\Services\TaskReminderService;
use App\Services\TaskTemplateService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:recover-stale', function () {
    $this->info('Recovered stale AI runs: '.app(AiRunService::class)->recoverStale());
})->purpose('Mark abandoned running AI requests failed without generating fake results');

Artisan::command('tasks:remind {--workspace=} {--user=} {--review-after=60}', function () {
    $after = filter_var($this->option('review-after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 10080]]);
    if ($after === false) {
        $this->error('review-after must be 0..10080 minutes');

        return 1;
    }
    $workspace = $this->option('workspace') ? Workspace::findOrFail($this->option('workspace')) : null;
    $count = 0;
    User::where('status', 'active')->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))->chunkById(100, function ($users) use ($workspace, $after, &$count) {
        foreach ($users as $user) {
            $count += app(TaskReminderService::class)->scan($user, $workspace, $after);
        }
    });
    $this->info('Queued reminders: '.$count);
})->purpose('Queue deduplicated in-app reminders; default review waiting60 minutes');
Artisan::command('tasks:generate-recurring', function () {
    $counts = app(TaskTemplateService::class)->due();
    $this->info(json_encode($counts));

    return empty($counts['migration_required']) ? 0 : 1;
})->purpose('Generate due task templates atomically with occurrence deduplication');
Schedule::command('tasks:generate-recurring')->everyMinute()->withoutOverlapping(5);
Schedule::command('tasks:remind')->hourly()->withoutOverlapping(55);
Schedule::command('ai:recover-stale')->everyMinute()->withoutOverlapping(5);
Schedule::command('auth:clear-resets')->hourly()->withoutOverlapping(5);
Schedule::call(fn () => Cache::put('ops:heartbeat:scheduler', time(), 600))->name('ops:heartbeat')->everyMinute();

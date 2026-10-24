<?php

namespace App\Providers;

use App\Contracts\AiProvider;
use App\Models\AiRun;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\Task;
use App\Models\Workspace;
use App\Observers\IntegrityObserver;
use App\Policies\AiRunPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ReportSnapshotPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use App\Policies\WorkspacePolicy;
use App\Services\AppShell;
use App\Services\HttpAiProvider;
use App\Services\NotificationService;
use App\Services\SettingService;
use App\Support\ReportAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AiProvider::class, function () {
            if (config('ai.provider') === 'gemini') {
                return $this->app->make(\App\Services\GeminiAiProvider::class);
            }

            return $this->app->make(HttpAiProvider::class);
        });
    }

    public function boot(): void
    {
        Queue::looping(function (Looping $event) {
            foreach (explode(',', (string) $event->queue) as $queue) {
                Cache::put('ops:heartbeat:worker:'.$event->connectionName.':'.$queue, time(), 600);
            }
        });
        foreach (['account-login' => 30, 'account-registration' => 10, 'account-recovery' => 5, 'account-reset' => 10, 'account-verification-mail' => 3, 'account-verification-link' => 10] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($limit)->by($request->user()?->id ?? $request->ip()));
        }
        Paginator::defaultView('pagination.native');
        Paginator::defaultSimpleView('pagination.simple');
        foreach (config('domain_schema.models') as $model) {
            $model::observe(IntegrityObserver::class);
        }
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(ReportSnapshot::class, ReportSnapshotPolicy::class);
        Gate::policy(AiRun::class, AiRunPolicy::class);
        View::composer(['workspaces.show', 'projects.show'], function ($view) {
            $data = $view->getData();
            $view->with('canViewReport', auth()->check() && app(ReportAccess::class)->scope(auth()->user(), $data['workspace'], $data['project'] ?? null));
            $view->with('canViewCalendar', auth()->check() && app(CalendarEventPolicy::class)->scope(auth()->user(), $data['workspace'], $data['project'] ?? null, 'calendar.view'));
        });
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        View::composer('components.layout', function ($view) {
            $user = auth()->user();
            $view->with('shell', app(AppShell::class)->build(request()));
            $view->with('preferences', $user ? app(SettingService::class)->values($user) : config('work_management.settings_defaults'));
            $view->with('unreadNotifications', $user ? app(NotificationService::class)->visible($user)->whereNull('read_at')->count() : 0);
        });
    }
}

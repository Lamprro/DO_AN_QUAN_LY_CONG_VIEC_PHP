<?php

namespace App\Providers;

use App\Authorization\ProjectAccess;
use App\Authorization\WorkspaceAccess;
use App\Models\AiRun;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ReportSnapshot;
use App\Models\Role;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Observers\IntegrityObserver;
use App\Policies\AiRunPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\ReportSnapshotPolicy;
use App\Policies\RolePolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimeEntryPolicy;
use App\Policies\WorkspacePolicy;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Ai\Providers\GeminiAiProvider;
use App\Services\Ai\Providers\HttpAiProvider;
use App\View\Composers\LayoutComposer;
use App\View\Composers\ScopeLinksComposer;
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
        $this->app->bind(AiProvider::class, fn () => $this->app->make(config('ai.provider') === 'gemini' ? GeminiAiProvider::class : HttpAiProvider::class));

        // Permission sources cache per request / queued job; see App\Authorization.
        $this->app->scoped(WorkspaceAccess::class);
        $this->app->scoped(ProjectAccess::class);
    }

    public function boot(): void
    {
        $this->registerPolicies();
        $this->registerModelHooks();
        $this->registerRateLimits();
        $this->registerViews();

        Queue::looping(function (Looping $event) {
            foreach (explode(',', (string) $event->queue) as $queue) {
                Cache::put('ops:heartbeat:worker:'.$event->connectionName.':'.$queue, time(), 600);
            }
        });
    }

    private function registerPolicies(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);
        Gate::policy(ReportSnapshot::class, ReportSnapshotPolicy::class);
        Gate::policy(AiRun::class, AiRunPolicy::class);
        Gate::policy(TimeEntry::class, TimeEntryPolicy::class);
    }

    private function registerModelHooks(): void
    {
        foreach (config('domain_schema.models') as $model) {
            $model::observe(IntegrityObserver::class);
        }
        // Any change to who-can-do-what drops the per-request permission cache.
        foreach ([User::class, Workspace::class, WorkspaceMember::class, Role::class, Project::class, ProjectMember::class] as $model) {
            $model::saved(fn () => $this->flushPermissionCache());
            $model::deleted(fn () => $this->flushPermissionCache());
        }
    }

    private function flushPermissionCache(): void
    {
        $this->app->make(WorkspaceAccess::class)->flush();
        $this->app->make(ProjectAccess::class)->flush();
    }

    private function registerRateLimits(): void
    {
        foreach (['account-login' => 30, 'account-registration' => 10, 'account-recovery' => 5, 'account-reset' => 10, 'account-verification-mail' => 3, 'account-verification-link' => 10] as $name => $limit) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($limit)->by($request->user()?->id ?? $request->ip()));
        }
    }

    private function registerViews(): void
    {
        Paginator::defaultView('pagination.native');
        Paginator::defaultSimpleView('pagination.simple');
        View::composer('components.layout', LayoutComposer::class);
        View::composer(['workspaces.show', 'projects.show'], ScopeLinksComposer::class);
    }
}

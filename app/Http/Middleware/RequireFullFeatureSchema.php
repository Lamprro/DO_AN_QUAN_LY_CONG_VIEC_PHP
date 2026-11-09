<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RequireFullFeatureSchema
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['task_watchers', 'task_templates', 'task_template_runs'] as $table) {
            if (! Schema::hasTable($table)) {
                return response()->view('errors.schema-upgrade', [], 503);
            }
        }
        // Team calendar proposals live in calendar_events since 2026_11_09_000001.
        if (! Schema::hasColumn('calendar_events', 'proposed_by')) {
            return response()->view('errors.schema-upgrade', [], 503);
        }

        return $next($request);
    }
}

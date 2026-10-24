<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class RequireFullFeatureSchema
{
    public function handle(Request $request, Closure $next)
    {
        foreach (['task_watchers', 'calendar_proposals', 'task_templates', 'task_template_runs'] as $table) {
            if (! Schema::hasTable($table)) {
                return response()->view('errors.schema-upgrade', [], 503);
            }
        }

        return $next($request);
    }
}

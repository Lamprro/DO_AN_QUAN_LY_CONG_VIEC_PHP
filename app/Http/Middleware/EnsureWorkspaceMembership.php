<?php

namespace App\Http\Middleware;

use App\Models\Workspace;
use App\Support\WorkspaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWorkspaceMembership
{
    public function __construct(private WorkspaceAccess $access) {}

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->route('workspace');
        abort_unless($workspace instanceof Workspace && $this->access->membership($request->user(), $workspace), 403, 'Bạn không còn là thành viên hoạt động của workspace này.');

        return $next($request);
    }
}

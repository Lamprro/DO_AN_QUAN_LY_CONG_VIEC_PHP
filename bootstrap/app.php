<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureWorkspaceMembership;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: ['hooks/github/*']);
        $middleware->alias(['active.user' => EnsureUserIsActive::class, 'workspace.member' => EnsureWorkspaceMembership::class]);
        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/workspaces');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (LockTimeoutException $error, Request $request) {
            return back()->withErrors(['invitation' => 'Thao tác đang được xử lý. Vui lòng thử lại.']);
        });
    })->create();

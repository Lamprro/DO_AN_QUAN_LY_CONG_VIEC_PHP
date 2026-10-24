<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireVerifiedAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('security.require_verified_email') && ! $request->user()->hasVerifiedEmail()
            && ! $request->routeIs('profile.*', 'password.update')) {
            return to_route('verification.notice');
        }

        return $next($request);
    }
}

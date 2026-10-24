<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustedProxyConfiguration extends TrustProxies
{
    public function handle(Request $request, Closure $next)
    {
        $this->proxies = config('security.trusted_proxies');
        $this->headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;

        return parent::handle($request, $next);
    }
}

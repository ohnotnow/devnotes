<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequirePasswordChange
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('sso.enabled')) {
            return $next($request);
        }

        if (! $request->user() || ! $request->user()->must_change_password) {
            return $next($request);
        }

        if ($request->routeIs('change-password')) {
            return $next($request);
        }

        return redirect()->route('change-password');
    }
}

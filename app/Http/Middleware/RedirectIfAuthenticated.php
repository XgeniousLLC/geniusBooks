<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->guard('web')->check()) {
            return redirect()->route('portal.home');
        }

        return $next($request);
    }
}

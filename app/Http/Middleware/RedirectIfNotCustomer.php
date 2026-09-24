<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfNotCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->guard('web')->check()) {
            return redirect()->route('portal.login');
        }

        if (! auth()->guard('web')->user()->is_active) {
            auth()->guard('web')->logout();
            return redirect()->route('portal.login')->withErrors(['email' => 'Your account is inactive.']);
        }

        return $next($request);
    }
}

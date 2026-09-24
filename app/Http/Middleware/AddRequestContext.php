<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds tenant/user identifiers to the shared log context so every log line and
 * captured error carries traceability.
 */
class AddRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        Context::add([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'company_id' => app(CompanyContext::class)->id(),
        ]);

        return $next($request);
    }
}

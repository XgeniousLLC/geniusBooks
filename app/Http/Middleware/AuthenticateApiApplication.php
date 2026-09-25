<?php

namespace App\Http\Middleware;

use App\Models\ApiApplication;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiApplication
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated. Missing Bearer token.'], 401);
        }

        $hash = ApiApplication::hashToken($token);
        $app = ApiApplication::withoutGlobalScope('company')
            ->where('token_hash', $hash)
            ->first();

        if (! $app) {
            return response()->json(['message' => 'Unauthenticated. Invalid API token.'], 401);
        }

        if ($app->isExpired()) {
            return response()->json(['message' => 'API token expired.'], 401);
        }

        $app->update(['last_used_at' => now()]);

        app(CompanyContext::class)->set($app->company_id);
        $request->attributes->set('apiApplication', $app);
        $request->attributes->set('apiCompanyId', $app->company_id);

        return $next($request);
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->header('Authorization');
        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return $request->query('token') ?: $request->input('token');
    }
}

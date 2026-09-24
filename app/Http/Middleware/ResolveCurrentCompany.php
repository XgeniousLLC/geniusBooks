<?php

namespace App\Http\Middleware;

use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active company for the authenticated tenant user and shares it
 * with the tenancy scope and the permission team resolver.
 */
class ResolveCurrentCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $context = app(CompanyContext::class);

        $sessionId = $request->session()->get('current_company_id');
        $company = $sessionId
            ? $user->activeCompanies()->where('companies.id', $sessionId)->first()
            : null;

        $company ??= $user->activeCompanies()->orderBy('company_user.id')->first();

        if (! $company) {
            $context->forget();

            return redirect()->route('portal.onboarding');
        }

        if (! $company->is_active) {
            $context->forget();

            return redirect()->route('portal.suspended');
        }

        $request->session()->put('current_company_id', $company->id);
        $context->set($company->id);
        Context::add('company_id', $company->id);

        // Apply the company's currency display preferences to money formatting.
        \App\Support\Money::configureFormatting(
            $company->currency,
            $company->currency_symbol,
            $company->currency_position,
        );

        // The active permission team changed: drop any cached role/permission
        // relations so authorization reflects the current company.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}

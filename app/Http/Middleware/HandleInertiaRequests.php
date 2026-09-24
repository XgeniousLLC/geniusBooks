<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'portal';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user('web');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                // Resolved lazily: the active company (and its permission team)
                // is set by route middleware that runs after this one.
                'roles' => fn () => $user ? $user->getRoleNames()->all() : [],
            ],
            'currentCompany' => function () {
                $context = app(CompanyContext::class);

                return $context->has()
                    ? Company::find($context->id())?->only(['id', 'name', 'slug', 'currency', 'logo_path'])
                    : null;
            },
            'companies' => fn () => $user
                ? $user->activeCompanies()->orderBy('name')->get(['companies.id', 'companies.name'])
                : [],
            'impersonating' => $request->session()->has('impersonator_admin_id'),
            'demo' => config('accounting.demo'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'status' => $request->session()->get('status'),
                'importResult' => $request->session()->get('importResult'),
                'importErrors' => $request->session()->get('importErrors'),
            ],
        ];
    }

    /**
     * Prevent browsers/proxies from caching portal pages.
     */
    public function handle(Request $request, \Closure $next)
    {
        /** @var \Symfony\Component\HttpFoundation\Response $response */
        $response = parent::handle($request, $next);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}

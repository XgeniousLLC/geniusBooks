<?php

namespace App\Http\Controllers\Portal;

use App\Enums\IntegrationProvider;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Integration;
use App\Services\Integrations\IntegrationManager;
use App\Services\Integrations\SyncService;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IntegrationController extends Controller
{
    public function index()
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $integrations = Integration::where('company_id', $company->id)->withCount('logs')->get();
        // Ensure all providers are represented
        $providers = collect(IntegrationProvider::cases())->map(function ($p) use ($integrations) {
            $existing = $integrations->firstWhere('provider.value', $p->value);
            return [
                'provider' => $p->value,
                'label' => $p->label(),
                'docs_url' => $p->docsUrl(),
                'api_base' => $p->apiBaseUrl(),
                'integration' => $existing,
            ];
        });

        return Inertia::render('Settings/Integrations', [
            'providers' => $providers,
        ]);
    }

    public function store(Request $request, IntegrationManager $manager)
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $data = $request->validate([
            'provider' => ['required', Rule::in(array_map(fn (IntegrationProvider $p) => $p->value, IntegrationProvider::cases()))],
            'access_token' => ['required','string','min:10'],
            'refresh_token' => ['nullable','string'],
            'external_id' => ['nullable','string','max:255'],
        ]);

        $provider = IntegrationProvider::from($data['provider']);
        $integration = $manager->connect($company, $provider, [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'external_id' => $data['external_id'] ?? null,
        ]);

        return back()->with('success', "{$provider->label()} connected.");
    }

    public function destroy(Integration $integration, IntegrationManager $manager)
    {
        Gate::authorize(Permission::ManageCompany);
        abort_unless($integration->company_id === $this->company()->id, 403);
        $manager->disconnect($integration);
        return back()->with('success', "{$integration->provider->label()} disconnected.");
    }

    public function sync(Request $request, Integration $integration, SyncService $sync)
    {
        Gate::authorize(Permission::ManageCompany);
        abort_unless($integration->company_id === $this->company()->id, 403);

        $data = $request->validate([
            'entity_type' => ['required', Rule::in(['customer','product','invoice','payment','expense'])],
            'entity_id' => ['required','integer'],
            'direction' => ['required', Rule::in(['push','pull'])],
        ]);

        $result = $data['direction'] === 'push'
            ? $sync->push($integration, $data['entity_type'], (int) $data['entity_id'])
            : $sync->pull($integration, $data['entity_type'], (string) $data['entity_id']);

        return back()->with($result['success'] ? 'success' : 'error', $result['log']->message ?? 'Sync completed.');
    }

    public function logs(Integration $integration)
    {
        Gate::authorize(Permission::ManageCompany);
        abort_unless($integration->company_id === $this->company()->id, 403);
        return response()->json($integration->logs()->latest()->limit(50)->get());
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

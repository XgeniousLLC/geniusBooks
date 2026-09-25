<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ApiApplication;
use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;

class ApiApplicationController extends Controller
{
    public function index(Response|RedirectResponse $response = null): Response
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $apps = ApiApplication::where('company_id', $company->id)->latest()->get(['id','name','last_used_at','expires_at','created_at']);

        return Inertia::render('Settings/ApiApplications', [
            'applications' => $apps,
            'flash' => session('plain_token') ? ['plain_token' => session('plain_token')] : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $data = $request->validate([
            'name' => ['required','string','max:100'],
            'expires_at' => ['nullable','date','after:now'],
        ]);

        $plain = ApiApplication::generateToken();
        $app = ApiApplication::create([
            'company_id' => $company->id,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'token_hash' => ApiApplication::hashToken($plain),
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        return redirect()->route('portal.settings.api.index')->with('plain_token', $plain)->with('success', "API application '{$app->name}' created. Copy the token now — it will not be shown again.");
    }

    public function destroy(ApiApplication $apiApplication): RedirectResponse
    {
        Gate::authorize(Permission::ManageCompany);
        // Ensure belongs to current company
        abort_unless($apiApplication->company_id === $this->company()->id, 403);
        $apiApplication->delete();
        return back()->with('success', 'API application revoked.');
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

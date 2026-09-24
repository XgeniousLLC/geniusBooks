<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\Invitation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $provisioning,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): View
    {
        $query = Company::query()->withCount('users');

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $companies = $query->latest()->paginate(15)->withQueryString();

        return view('admin.companies.index', compact('companies'));
    }

    public function show(Company $company): View
    {
        $company->loadCount('users');

        $counts = [
            'users' => $company->users_count,
            'active_users' => $company->activeUsers()->count(),
            'pending_invitations' => Invitation::withoutCompanyScope()
                ->where('company_id', $company->id)->pending()->count(),
            'document_sequences' => DocumentSequence::withoutCompanyScope()
                ->where('company_id', $company->id)->count(),
            'audit_logs' => AuditLog::where('company_id', $company->id)->count(),
        ];

        $members = $company->users()->orderBy('name')->get();

        return view('admin.companies.show', compact('company', 'counts', 'members'));
    }

    public function suspend(Company $company): RedirectResponse
    {
        $company->update(['is_active' => false]);

        $this->auditLogger->log('suspended', $company, [], ['is_active' => false], null, $company->id);

        return back()->with('success', "{$company->name} suspended.");
    }

    public function reactivate(Company $company): RedirectResponse
    {
        $company->update(['is_active' => true]);

        $this->auditLogger->log('reactivated', $company, [], ['is_active' => true], null, $company->id);

        return back()->with('success', "{$company->name} reactivated.");
    }

    public function impersonate(Request $request, Company $company): RedirectResponse
    {
        $user = $request->filled('user_id')
            ? $company->users()->whereKey($request->integer('user_id'))->first()
            : $this->ownerOf($company);

        abort_if($user === null, 422, 'No member available to impersonate.');

        Auth::guard('web')->login($user);

        $request->session()->put('impersonator_admin_id', Auth::guard('admin')->id());
        $request->session()->put('current_company_id', $company->id);
        $request->session()->regenerate();

        $this->auditLogger->log('impersonated', $company, [], [
            'admin_id' => Auth::guard('admin')->id(),
            'target_user_id' => $user->id,
        ], null, $company->id);

        return redirect()->route('portal.home')
            ->with('success', "Impersonating {$user->name}.");
    }

    private function ownerOf(Company $company): ?User
    {
        $members = $company->users()->get();

        foreach ($members as $member) {
            if ($this->provisioning->roleOf($company, $member)?->value === 'owner') {
                return $member;
            }
        }

        return $members->first();
    }
}

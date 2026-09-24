<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Exceptions\LastOwnerException;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembersController extends Controller
{
    public function __construct(private readonly CompanyProvisioningService $provisioning) {}

    public function index(): Response
    {
        Gate::authorize(Permission::ManageUsers);

        $company = $this->company();

        $members = $company->users()
            ->orderBy('name')
            ->get()
            ->map(function (User $user) use ($company) {
                $role = $this->provisioning->roleOf($company, $user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $role?->value,
                    'role_label' => $role?->label(),
                    'is_active' => (bool) $user->pivot->is_active,
                    'is_self' => $user->id === auth()->id(),
                ];
            })
            ->values();

        $invitations = Invitation::query()
            ->where('company_id', $company->id)
            ->pending()
            ->latest()
            ->get()
            ->map(fn (Invitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->value,
                'role_label' => $invitation->role->label(),
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Settings/Users', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => collect(CompanyRole::cases())
                ->map(fn (CompanyRole $role) => ['value' => $role->value, 'label' => $role->label()])
                ->values(),
        ]);
    }

    public function updateRole(Request $request, User $member): RedirectResponse
    {
        Gate::authorize(Permission::ManageUsers);

        $company = $this->company();
        $this->ensureMember($company, $member);

        $data = $request->validate([
            'role' => ['required', Rule::in(CompanyRole::names())],
        ]);

        try {
            $this->provisioning->syncRole($company, $member, CompanyRole::from($data['role']));
        } catch (LastOwnerException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Role updated.');
    }

    public function deactivate(User $member): RedirectResponse
    {
        Gate::authorize(Permission::ManageUsers);

        $company = $this->company();
        $this->ensureMember($company, $member);

        if ($member->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate yourself.');
        }

        try {
            $this->provisioning->deactivateMember($company, $member);
        } catch (LastOwnerException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Member deactivated.');
    }

    public function reactivate(User $member): RedirectResponse
    {
        Gate::authorize(Permission::ManageUsers);

        $company = $this->company();
        $this->ensureMember($company, $member);

        $this->provisioning->reactivateMember($company, $member);

        return back()->with('success', 'Member reactivated.');
    }

    public function destroy(User $member): RedirectResponse
    {
        Gate::authorize(Permission::ManageUsers);

        $company = $this->company();
        $this->ensureMember($company, $member);

        if ($member->id === auth()->id()) {
            return back()->with('error', 'You cannot remove yourself.');
        }

        try {
            $this->provisioning->removeMember($company, $member);
        } catch (LastOwnerException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Member removed.');
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }

    private function ensureMember(Company $company, User $member): void
    {
        abort_unless($company->users()->whereKey($member->id)->exists(), 404);
    }
}

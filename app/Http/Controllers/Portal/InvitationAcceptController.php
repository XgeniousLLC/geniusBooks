<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationAcceptController extends Controller
{
    public function __construct(private readonly CompanyProvisioningService $provisioning) {}

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->find($token);

        if ($invitation->isAccepted()) {
            return redirect()->route('portal.login')
                ->with('status', 'This invitation has already been accepted.');
        }

        return Inertia::render('Invitations/Accept', [
            'token' => $token,
            'invitation' => [
                'email' => $invitation->email,
                'role_label' => $invitation->role->label(),
                'company' => $invitation->company->name,
            ],
            'expired' => $invitation->isExpired(),
            'authenticated' => Auth::guard('web')->check(),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->find($token);

        if ($invitation->isAccepted()) {
            return redirect()->route('portal.login')
                ->with('status', 'This invitation has already been accepted.');
        }

        if ($invitation->isExpired()) {
            return back()->with('error', 'This invitation has expired.');
        }

        $company = $invitation->company;
        $user = Auth::guard('web')->user();

        if ($user) {
            if (strcasecmp($user->email, $invitation->email) !== 0) {
                return back()->withErrors([
                    'email' => 'Please sign in with the invited email address ('.$invitation->email.').',
                ]);
            }
        } else {
            if (User::where('email', $invitation->email)->exists()) {
                return redirect()->route('portal.login')
                    ->with('status', 'Please sign in to accept this invitation.');
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', PasswordRule::defaults()],
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => Hash::make($data['password']),
                'is_active' => true,
            ]);

            // The invitation was delivered to this address, so treat it as verified.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        DB::transaction(function () use ($company, $user, $invitation) {
            if ($company->users()->whereKey($user->id)->exists()) {
                $company->users()->updateExistingPivot($user->id, ['is_active' => true]);
            } else {
                $company->users()->attach($user->id, ['is_active' => true]);
            }

            $this->provisioning->ensureRoles($company);
            $this->provisioning->assignRole($company, $user, $invitation->role->value);

            $invitation->update(['accepted_at' => now()]);
        });

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('current_company_id', $company->id);

        return redirect()->route('portal.home')
            ->with('success', 'Welcome to '.$company->name.'.');
    }

    private function find(string $token): Invitation
    {
        return Invitation::withoutCompanyScope()
            ->with('company')
            ->where('token', $token)
            ->firstOrFail();
    }
}

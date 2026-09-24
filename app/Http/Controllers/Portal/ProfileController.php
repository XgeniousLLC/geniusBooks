<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('Profile/Edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.auth()->id()],
        ]);

        auth()->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $provisioning = app(CompanyProvisioningService::class);
        $context = app(\App\Support\CompanyContext::class);

        foreach ($user->activeCompanies as $company) {
            $context->set($company->id);
            $user->unsetRelation('roles')->unsetRelation('permissions');

            if ($provisioning->isLastOwner($company, $user)) {
                $context->forget();

                return back()->with('error', 'Transfer ownership of '.$company->name.' before deleting your account.');
            }
        }

        $context->forget();

        // Deactivate and anonymise the account, then remove memberships. The
        // row is retained so historical records remain referentially intact.
        DB::table('model_has_roles')
            ->where('model_id', $user->id)
            ->where('model_type', \App\Models\User::class)
            ->delete();

        $user->companies()->detach();

        $user->forceFill([
            'name' => 'Deleted user',
            'email' => 'deleted+'.$user->id.'@example.invalid',
            'email_verified_at' => null,
            'is_active' => false,
            'remember_token' => null,
        ])->save();

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('status', 'Your account has been deleted.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageUsers);

        $company = Company::findOrFail(app(CompanyContext::class)->id());

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(CompanyRole::names())],
        ]);

        $email = strtolower($data['email']);

        if ($company->users()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'That person is already a member.']);
        }

        $alreadyPending = Invitation::query()
            ->where('company_id', $company->id)
            ->where('email', $email)
            ->pending()
            ->exists();

        if ($alreadyPending) {
            return back()->withErrors(['email' => 'An invitation is already pending for this email.']);
        }

        $invitation = Invitation::create([
            'email' => $email,
            'role' => $data['role'],
            'invited_by' => auth()->id(),
            'expires_at' => now()->addDays(7),
        ]);

        Mail::to($email)->send(new InvitationMail($invitation));

        return back()->with('success', 'Invitation sent.');
    }

    public function destroy(Invitation $invitation): RedirectResponse
    {
        Gate::authorize('delete', $invitation);

        $invitation->delete();

        return back()->with('success', 'Invitation revoked.');
    }
}

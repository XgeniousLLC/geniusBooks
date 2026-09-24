<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function stop(Request $request): RedirectResponse
    {
        if (! $request->session()->has('impersonator_admin_id')) {
            return redirect()->route('portal.home');
        }

        $request->session()->forget('impersonator_admin_id');
        $request->session()->forget('current_company_id');

        Auth::guard('web')->logout();

        $request->session()->regenerate();

        return redirect()->route('admin.companies.index')
            ->with('success', 'Impersonation ended.');
    }
}

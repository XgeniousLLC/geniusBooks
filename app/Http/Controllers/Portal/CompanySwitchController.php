<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CompanySwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer'],
        ]);

        $company = $request->user()
            ->activeCompanies()
            ->where('companies.id', $data['company_id'])
            ->first();

        if (! $company) {
            abort(403);
        }

        $request->session()->put('current_company_id', $company->id);

        return redirect()->route('portal.home')
            ->with('success', "Switched to {$company->name}.");
    }
}

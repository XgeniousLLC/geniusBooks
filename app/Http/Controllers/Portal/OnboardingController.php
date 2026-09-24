<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\CompanyProvisioningService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->activeCompanies()->exists()) {
            return redirect()->route('portal.home');
        }

        return Inertia::render('Onboarding/Wizard', $this->formProps(false, route('portal.onboarding.store')));
    }

    public function store(
        Request $request,
        CompanyProvisioningService $provisioning,
    ): RedirectResponse {
        if ($request->user()->activeCompanies()->exists()) {
            return redirect()->route('portal.home');
        }

        $company = $provisioning->createFor($request->user(), $this->payload($request));

        $request->session()->put('current_company_id', $company->id);

        return redirect()->route('portal.home')->with('success', 'Your business is ready.');
    }

    /**
     * Add another business to the current account.
     */
    public function create(): Response
    {
        return Inertia::render('Onboarding/Wizard', $this->formProps(true, route('portal.businesses.store')));
    }

    public function storeBusiness(
        Request $request,
        CompanyProvisioningService $provisioning,
    ): RedirectResponse {
        $company = $provisioning->createFor($request->user(), $this->payload($request));

        $request->session()->put('current_company_id', $company->id);

        return redirect()->route('portal.home')
            ->with('success', $company->name.' is ready.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', 'string', Rule::in(config('accounting.currencies'))],
            'timezone' => ['required', 'timezone'],
            'financial_year_start_month' => ['required', 'integer', 'between:1,12'],
            'financial_year_start_day' => ['required', 'integer', 'between:1,31'],
            'tax_registration_number' => ['nullable', 'string', 'max:100'],
            'tax_inclusive' => ['boolean'],
            'default_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_number_padding' => ['required', 'integer', 'between:1,10'],
            'default_payment_terms_days' => ['required', 'integer', 'between:0,365'],
        ]);

        $data['country'] = isset($data['country']) ? strtoupper($data['country']) : null;
        $data['currency'] = strtoupper($data['currency']);
        $data['onboarded_at'] = now();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(bool $adding, string $submitUrl): array
    {
        return [
            'adding' => $adding,
            'submitUrl' => $submitUrl,
            'currencies' => config('accounting.currencies'),
            'paymentTerms' => config('accounting.payment_terms'),
            'timezones' => timezone_identifiers_list(),
            'defaults' => [
                'currency' => 'USD',
                'timezone' => config('app.timezone', 'UTC'),
                'financial_year_start_month' => 1,
                'invoice_prefix' => 'INV-',
                'invoice_number_padding' => 4,
                'default_payment_terms_days' => 15,
                'tax_inclusive' => false,
                'default_tax_rate' => 0,
            ],
        ];
    }
}

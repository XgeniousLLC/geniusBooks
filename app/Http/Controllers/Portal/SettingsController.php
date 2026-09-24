<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use App\Services\Export\TenantDataExporter;
use App\Support\CompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingsController extends Controller
{
    public function __construct(private readonly DocumentNumberService $numbers) {}

    public function business(): Response
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        return Inertia::render('Settings/Business', [
            'settings' => [
                'name' => $company->name,
                'email' => $company->email,
                'phone' => $company->phone,
                'address' => $company->address,
                'country' => $company->country,
                'currency' => $company->currency,
                'currency_symbol' => $company->currency_symbol,
                'currency_position' => $company->currency_position ?: 'prefix',
                'timezone' => $company->timezone,
                'financial_year_start_month' => $company->financial_year_start_month,
                'financial_year_start_day' => $company->financial_year_start_day,
                'logo_url' => $company->logo_path ? route('portal.settings.logo') : null,
            ],
            'currencies' => config('accounting.currencies'),
            'timezones' => timezone_identifiers_list(),
        ]);
    }

    public function updateBusiness(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'country' => ['nullable', 'string', 'size:2'],
            'currency' => ['required', Rule::in(config('accounting.currencies'))],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'currency_position' => ['required', Rule::in(['prefix', 'suffix'])],
            'timezone' => ['required', 'timezone'],
            'financial_year_start_month' => ['required', 'integer', 'between:1,12'],
            'financial_year_start_day' => ['required', 'integer', 'between:1,31'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            if ($company->logo_path) {
                Storage::disk('local')->delete($company->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('company/'.$company->id, 'local');
        }

        $data['country'] = isset($data['country']) ? strtoupper($data['country']) : null;
        $data['currency'] = strtoupper($data['currency']);
        $data['currency_symbol'] = ($data['currency_symbol'] ?? null) ?: null;
        unset($data['logo']);

        $company->update($data);

        return back()->with('success', 'Business settings updated.');
    }

    public function logo(): StreamedResponse
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        abort_unless($company->logo_path && Storage::disk('local')->exists($company->logo_path), 404);

        return Storage::disk('local')->response($company->logo_path);
    }

    public function invoices(): Response
    {
        Gate::authorize(Permission::ManageSettings);
        $company = $this->company();

        return Inertia::render('Settings/Invoices', [
            'settings' => [
                'invoice_prefix' => $company->invoice_prefix,
                'invoice_number_padding' => $company->invoice_number_padding,
                'default_payment_terms_days' => $company->default_payment_terms_days,
                'default_invoice_terms' => $company->default_invoice_terms,
                'invoice_footer' => $company->invoice_footer,
            ],
            'paymentTerms' => config('accounting.payment_terms'),
            'nextNumber' => $this->numbers->preview($company, 'invoice'),
        ]);
    }

    public function updateInvoices(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings);
        $company = $this->company();

        $data = $request->validate([
            'invoice_prefix' => ['required', 'string', 'max:20'],
            'invoice_number_padding' => ['required', 'integer', 'between:1,10'],
            'default_payment_terms_days' => ['required', 'integer', 'between:0,365'],
            'default_invoice_terms' => ['nullable', 'string', 'max:5000'],
            'invoice_footer' => ['nullable', 'string', 'max:1000'],
        ]);

        $company->update($data);

        // Keep the live sequence in step with the new prefix/padding while no
        // invoices have been issued yet.
        if (! Invoice::withoutCompanyScope()->where('company_id', $company->id)->exists()) {
            \App\Models\DocumentSequence::withoutCompanyScope()->updateOrCreate(
                ['company_id' => $company->id, 'type' => 'invoice'],
                ['prefix' => $data['invoice_prefix'], 'padding' => $data['invoice_number_padding']],
            );
        }

        return back()->with('success', 'Invoice settings updated.');
    }

    public function export(TenantDataExporter $exporter): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        Gate::authorize(Permission::ManageCompany);

        $path = $exporter->export($this->company());

        return response()
            ->download($path, 'export-'.now()->format('Ymd-His').'.zip')
            ->deleteFileAfterSend(true);
    }

    public function payments(): Response
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        return Inertia::render('Settings/Payments', [
            'settings' => [
                'online_payments_enabled' => (bool) $company->online_payments_enabled,
                'has_secret' => ! empty($company->stripe_secret_key),
                'has_webhook_secret' => ! empty($company->stripe_webhook_secret),
                'deposit_account_id' => $company->stripe_deposit_account_id,
            ],
            'accounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'webhookUrl' => route('portal.webhooks.stripe'),
        ]);
    }

    public function updatePayments(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageCompany);
        $company = $this->company();

        $data = $request->validate([
            'online_payments_enabled' => ['boolean'],
            'stripe_secret_key' => ['nullable', 'string', 'max:255'],
            'stripe_webhook_secret' => ['nullable', 'string', 'max:255'],
            'stripe_deposit_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $company->id)],
        ]);

        $enabled = $request->boolean('online_payments_enabled');

        if ($enabled && empty($data['stripe_secret_key']) && empty($company->stripe_secret_key)) {
            return back()->withErrors(['stripe_secret_key' => 'A Stripe secret key is required to enable online payments.']);
        }

        if ($enabled && empty($data['stripe_deposit_account_id'])) {
            return back()->withErrors(['stripe_deposit_account_id' => 'Choose the account that receives online payments.']);
        }

        $update = [
            'online_payments_enabled' => $enabled,
            'stripe_deposit_account_id' => $data['stripe_deposit_account_id'] ?? null,
        ];

        if (! empty($data['stripe_secret_key'])) {
            $update['stripe_secret_key'] = $data['stripe_secret_key'];
        }

        if (! empty($data['stripe_webhook_secret'])) {
            $update['stripe_webhook_secret'] = $data['stripe_webhook_secret'];
        }

        $company->update($update);

        return back()->with('success', 'Online payment settings updated.');
    }

    public function tax(): Response
    {
        Gate::authorize(Permission::ManageSettings);
        $company = $this->company();

        return Inertia::render('Settings/Tax', [
            'settings' => [
                'tax_registration_number' => $company->tax_registration_number,
                'default_tax_rate' => $company->default_tax_rate,
                'tax_inclusive' => (bool) $company->tax_inclusive,
            ],
        ]);
    }

    public function updateTax(Request $request): RedirectResponse
    {
        Gate::authorize(Permission::ManageSettings);
        $company = $this->company();

        $data = $request->validate([
            'tax_registration_number' => ['nullable', 'string', 'max:100'],
            'default_tax_rate' => ['required', 'numeric', 'between:0,100'],
            'tax_inclusive' => ['boolean'],
        ]);

        $data['tax_inclusive'] = $request->boolean('tax_inclusive');

        $company->update($data);

        return back()->with('success', 'Tax settings updated.');
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

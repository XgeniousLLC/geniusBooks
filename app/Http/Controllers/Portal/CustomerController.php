<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreCustomerRequest;
use App\Http\Requests\Portal\UpdateCustomerRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LedgerAccount;
use App\Services\Accounting\LedgerPostingService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Customer::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'name',
            'allowedSorts' => ['name', 'email', 'company_name', 'created_at'],
            'filters' => ['status'],
        ]);

        $query = Customer::query();
        $this->applyStatus($query, $list->filters['status'] ?? null);
        $list->apply($query, ['name', 'email', 'company_name', 'phone']);

        return Inertia::render('Customers/Index', [
            'customers' => $list->paginate($query),
            'filters' => $list->meta(),
            'can' => [
                'create' => Gate::allows('create', Customer::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Customer::class);

        return Inertia::render('Customers/Form', [
            'customer' => null,
            'companyCurrency' => $this->company()->currency,
            'paymentTerms' => config('accounting.payment_terms'),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($this->payload($request->validated()));

        return redirect()
            ->route('portal.customers.show', $customer)
            ->with('success', 'Customer created.');
    }

    public function show(Customer $customer): Response
    {
        Gate::authorize('view', $customer);

        $currency = $customer->effectiveCurrency();
        $payments = app(\App\Services\Accounting\PaymentService::class);

        $invoices = $customer->invoices()
            ->orderByDesc('issue_date')
            ->limit(25)
            ->get()
            ->map(function ($invoice) use ($currency) {
                $status = $invoice->displayStatus();

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'issue_date' => $invoice->issue_date->toDateString(),
                    'due_date' => $invoice->due_date->toDateString(),
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'status_class' => $status->badgeClasses(),
                    'total_display' => Money::of((int) $invoice->total, $currency)->format(),
                    'balance_display' => Money::of($invoice->balance(), $currency)->format(),
                ];
            })
            ->values();

        $paymentRows = $customer->payments()
            ->whereNull('voided_at')
            ->orderByDesc('date')
            ->limit(25)
            ->get()
            ->map(fn ($payment) => [
                'id' => $payment->id,
                'date' => $payment->date->toDateString(),
                'amount_display' => Money::of((int) $payment->amount, $currency)->format(),
                'method_label' => \App\Enums\PaymentMethod::tryFrom($payment->method)?->label() ?? $payment->method,
                'reference' => $payment->reference,
            ])
            ->values();

        $settledInvoices = $customer->invoices()
            ->whereNotIn('status', [\App\Enums\InvoiceStatus::Cancelled->value, \App\Enums\InvoiceStatus::Draft->value])
            ->get();

        $invoiced = (int) $settledInvoices->sum('total');
        $outstanding = (int) $settledInvoices->sum(fn ($invoice) => $invoice->balance());

        $opening = (int) \App\Models\Transaction::withoutCompanyScope()
            ->where('company_id', $customer->company_id)
            ->where('source_type', Customer::class)
            ->where('source_id', $customer->id)
            ->get()
            ->sum(fn ($transaction) => $transaction->signedAmount());

        return Inertia::render('Customers/Show', [
            'customer' => $customer->only([
                'id', 'name', 'company_name', 'email', 'phone', 'billing_address',
                'shipping_address', 'tax_id', 'currency', 'payment_terms_days',
                'notes', 'is_active', 'created_at',
            ]),
            'summary' => [
                'invoiced' => Money::of($invoiced, $currency)->format(),
                'paid' => Money::of((int) $customer->payments()->whereNull('voided_at')->sum('amount'), $currency)->format(),
                'credited' => Money::of((int) $customer->creditNotes()->sum('amount'), $currency)->format(),
                'outstanding' => Money::of($outstanding, $currency)->format(),
                'opening' => Money::of($opening, $currency)->format(),
                'credit_balance' => Money::of($payments->customerCredit($customer), $currency)->format(),
            ],
            'invoices' => $invoices,
            'payments' => $paymentRows,
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::authorize('update', $customer);

        return Inertia::render('Customers/Form', [
            'customer' => $customer->only([
                'id', 'name', 'company_name', 'email', 'phone', 'billing_address',
                'shipping_address', 'tax_id', 'payment_terms_days', 'notes', 'is_active',
            ]),
            'companyCurrency' => $this->company()->currency,
            'paymentTerms' => config('accounting.payment_terms'),
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($this->payload($request->validated()));

        return redirect()
            ->route('portal.customers.show', $customer)
            ->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::authorize('delete', $customer);

        $customer->delete();

        return redirect()
            ->route('portal.customers.index')
            ->with('success', 'Customer archived.');
    }

    public function bulk(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Customer::class);

        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'action' => ['required', \Illuminate\Validation\Rule::in(['archive', 'activate'])],
        ]);

        $active = $data['action'] === 'activate';
        $count = Customer::whereIn('id', $data['ids'])->update(['is_active' => $active]);

        return back()->with('success', $count.' customer(s) '.($active ? 'activated' : 'archived').'.');
    }

    public function storeOpeningBalance(Request $request, Customer $customer): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'type' => ['required', 'in:debit,credit'],
        ]);

        $company = $this->company();
        $receivable = LedgerAccount::query()
            ->where('company_id', $company->id)
            ->where('code', '1300')
            ->first();

        $this->ledger->post($company, [
            'bank_account_id' => null,
            'ledger_account_id' => $receivable?->id,
            'type' => \App\Enums\TransactionType::Adjustment->value,
            'direction' => $data['type'] === 'debit' ? 'in' : 'out',
            'amount' => Money::fromDecimal((string) $data['amount'], $customer->effectiveCurrency())->amount(),
            'currency' => $customer->effectiveCurrency(),
            'description' => 'Opening balance for '.$customer->name,
            'occurred_on' => $data['date'],
            'source' => $customer,
            'reason' => 'Opening balance',
        ]);

        return back()->with('success', 'Opening balance recorded.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payload(array $data): array
    {
        $company = $this->company();

        // A company is locked to a single currency at launch.
        $data['currency'] = $company->currency;
        $data['payment_terms_days'] ??= $company->default_payment_terms_days;

        return $data;
    }

    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

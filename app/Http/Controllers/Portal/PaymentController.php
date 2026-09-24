<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StorePaymentRequest;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Accounting\PaymentReceiptService;
use App\Services\Accounting\PaymentService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $payments,
        private readonly PaymentReceiptService $receipts,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Payment::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'date',
            'allowedSorts' => ['date', 'amount', 'created_at'],
            'filters' => ['customer_id', 'bank_account_id', 'method'],
        ]);

        $query = Payment::query()->with(['customer:id,name']);

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('reference', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term));
            });
        }

        foreach (['customer_id', 'bank_account_id', 'method'] as $filter) {
            if (! empty($list->filters[$filter])) {
                $query->where($filter, $list->filters[$filter]);
            }
        }

        $list->apply($query, []);

        return Inertia::render('Payments/Index', [
            'payments' => $list->paginate($query)->through(fn (Payment $payment) => $this->presentRow($payment)),
            'filters' => $list->meta(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => BankAccount::query()->orderBy('name')->get(['id', 'name']),
            'methods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()])
                ->values(),
            'can' => ['create' => Gate::allows('create', Payment::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Payment::class);

        $company = $this->company();
        $selectedInvoice = null;

        if ($request->filled('invoice')) {
            $selectedInvoice = Invoice::query()->find($request->integer('invoice'));
        }

        return Inertia::render('Payments/Form', [
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'accounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name', 'currency']),
            'methods' => collect(PaymentMethod::cases())
                ->map(fn (PaymentMethod $method) => ['value' => $method->value, 'label' => $method->label()])
                ->values(),
            'openInvoices' => $this->openInvoices($company),
            'selectedInvoiceId' => $selectedInvoice?->id,
            'defaults' => ['date' => now()->toDateString()],
            'currency' => $company->currency,
        ]);
    }

    public function store(StorePaymentRequest $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validated();

        $account = BankAccount::query()->findOrFail($data['bank_account_id']);
        $currency = $account->currency ?: $company->currency;

        $allocations = collect($data['allocations'] ?? [])
            ->map(fn (array $allocation) => [
                'invoice_id' => (int) $allocation['invoice_id'],
                'amount' => Money::fromDecimal((string) $allocation['amount'], $currency)->amount(),
            ])
            ->filter(fn (array $allocation) => $allocation['amount'] > 0)
            ->values()
            ->all();

        $payment = $this->payments->record($company, [
            'customer_id' => (int) $data['customer_id'],
            'bank_account_id' => (int) $data['bank_account_id'],
            'date' => $data['date'],
            'amount' => Money::fromDecimal((string) $data['amount'], $currency)->amount(),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'allocations' => $allocations,
        ]);

        if ($request->boolean('send_receipt') && $company->notify_payment_received) {
            try {
                $this->receipts->send($payment);
            } catch (RuntimeException $e) {
                return redirect()->route('portal.payments.show', $payment)
                    ->with('error', 'Payment recorded, but the receipt was not sent: '.$e->getMessage());
            }
        }

        return redirect()
            ->route('portal.payments.show', $payment)
            ->with('success', 'Payment recorded.');
    }

    public function show(Payment $payment): Response
    {
        Gate::authorize('view', $payment);
        $payment->load(['customer', 'bankAccount', 'allocations.invoice']);

        return Inertia::render('Payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'date' => $payment->date->toDateString(),
                'amount_display' => Money::of((int) $payment->amount, $payment->currency())->format(),
                'unapplied_display' => Money::of($payment->unapplied(), $payment->currency())->format(),
                'method_label' => PaymentMethod::tryFrom($payment->method)?->label() ?? $payment->method,
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'is_voided' => $payment->isVoided(),
                'void_reason' => $payment->void_reason,
                'customer' => [
                    'id' => $payment->customer->id,
                    'name' => $payment->customer->name,
                    'email' => $payment->customer->email,
                ],
                'account' => $payment->bankAccount?->name,
                'allocations' => $payment->allocations->map(fn ($allocation) => [
                    'invoice_id' => $allocation->invoice_id,
                    'number' => $allocation->invoice?->number,
                    'amount_display' => Money::of((int) $allocation->amount, $payment->currency())->format(),
                ])->values(),
            ],
            'can' => [
                'void' => Gate::allows('void', $payment),
                'receipt' => (bool) $payment->customer->email && ! $payment->isVoided(),
            ],
        ]);
    }

    public function emailReceipt(Payment $payment): RedirectResponse
    {
        Gate::authorize('view', $payment);

        if (! $payment->company->notify_payment_received) {
            return back()->with('error', 'Payment receipts are disabled in Email settings.');
        }

        try {
            $this->receipts->send($payment);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Receipt emailed to '.$payment->customer->email.'.');
    }

    public function void(Request $request, Payment $payment): RedirectResponse
    {
        Gate::authorize('void', $payment);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $this->payments->void($payment, $data['reason']);

        return redirect()
            ->route('portal.payments.show', $payment)
            ->with('success', 'Payment voided.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'date' => $payment->date->toDateString(),
            'customer_name' => $payment->customer?->name,
            'method_label' => PaymentMethod::tryFrom($payment->method)?->label() ?? $payment->method,
            'reference' => $payment->reference,
            'amount_display' => Money::of((int) $payment->amount, $payment->currency())->format(),
            'is_voided' => $payment->isVoided(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function openInvoices(Company $company)
    {
        return Invoice::query()
            ->with('customer:id,name')
            ->whereIn('status', ['sent', 'viewed'])
            ->whereRaw('total - amount_paid - credit_total > 0')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer?->name,
                'due_date' => $invoice->due_date->toDateString(),
                'balance' => $invoice->balance(),
                'balance_display' => Money::of($invoice->balance(), $invoice->currency)->format(),
            ])
            ->values();
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

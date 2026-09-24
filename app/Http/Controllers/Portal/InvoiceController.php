<?php

namespace App\Http\Controllers\Portal;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreInvoiceRequest;
use App\Http\Requests\Portal\UpdateInvoiceRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\DocumentNumberService;
use App\Services\Documents\InvoicePdfService;
use App\Services\Invoicing\InvoiceDeliveryService;
use App\Services\Invoicing\InvoiceService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly DocumentNumberService $numbers,
        private readonly InvoiceDeliveryService $delivery,
        private readonly InvoicePdfService $pdf,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Invoice::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'issue_date',
            'allowedSorts' => ['number', 'issue_date', 'due_date', 'total'],
            'filters' => ['status', 'customer_id'],
        ]);

        $query = Invoice::query()->with('customer:id,name');

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term));
            });
        }

        $this->applyStatusFilter($query, $list->filters['status'] ?? null);

        if (! empty($list->filters['customer_id'])) {
            $query->where('customer_id', $list->filters['customer_id']);
        }

        $list->apply($query, []);

        $company = $this->company();

        return Inertia::render('Invoices/Index', [
            'invoices' => $list->paginate($query)->through(fn (Invoice $invoice) => $this->presentRow($invoice, $company)),
            'filters' => $list->meta(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(InvoiceStatus::cases())
                ->map(fn (InvoiceStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->values(),
            'can' => ['create' => Gate::allows('create', Invoice::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Invoice::class);

        $company = $this->company();
        $issueDate = now()->toDateString();

        return Inertia::render('Invoices/Form', [
            'invoice' => null,
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'products' => $this->productOptions($company),
            'currency' => $company->currency,
            'taxInclusive' => (bool) $company->tax_inclusive,
            'nextNumber' => $this->numbers->preview($company, 'invoice'),
            'defaults' => [
                'issue_date' => $issueDate,
                'due_date' => now()->addDays($company->default_payment_terms_days)->toDateString(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        $invoice = $this->invoices->create($this->company(), $request->validated());

        return redirect()
            ->route('portal.invoices.show', $invoice)
            ->with('success', 'Invoice created.');
    }

    public function show(Invoice $invoice): Response
    {
        Gate::authorize('view', $invoice);

        $invoice->load(['customer', 'items']);

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->presentDetail($invoice),
            'accounts' => \App\Models\BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'can' => [
                'update' => Gate::allows('update', $invoice),
                'delete' => Gate::allows('delete', $invoice),
                'cancel' => Gate::allows('cancel', $invoice),
                'send' => $invoice->isDraft() && Gate::allows('update', $invoice),
                'duplicate' => Gate::allows('create', Invoice::class),
                'credit_note' => ! $invoice->isCancelled()
                    && $invoice->balance() > 0
                    && Gate::allows('create', \App\Models\CreditNote::class),
            ],
        ]);
    }

    public function edit(Invoice $invoice): Response|RedirectResponse
    {
        Gate::authorize('view', $invoice);

        if (! $invoice->isEditable()) {
            return redirect()->route('portal.invoices.show', $invoice)
                ->with('error', 'Only draft invoices can be edited. Issue a credit note instead.');
        }

        Gate::authorize('update', $invoice);

        $company = $this->company();
        $invoice->load('items');

        return Inertia::render('Invoices/Form', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'customer_id' => $invoice->customer_id,
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_value,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
                'items' => $invoice->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => Money::of((int) $item->unit_price, $company->currency)->toDecimal(),
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'tax_rate' => $item->tax_rate,
                ])->values(),
            ],
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'products' => $this->productOptions($company),
            'currency' => $company->currency,
            'taxInclusive' => (bool) $invoice->tax_inclusive,
            'nextNumber' => $invoice->number,
            'defaults' => [
                'issue_date' => $invoice->issue_date->toDateString(),
                'due_date' => $invoice->due_date->toDateString(),
            ],
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->invoices->update($invoice, $request->validated());

        return redirect()
            ->route('portal.invoices.show', $invoice)
            ->with('success', 'Invoice updated.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('portal.invoices.index')->with('success', 'Draft invoice deleted.');
    }

    public function duplicate(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('view', $invoice);
        Gate::authorize('create', Invoice::class);

        $copy = $this->invoices->duplicate($invoice);

        return redirect()
            ->route('portal.invoices.edit', $copy)
            ->with('success', 'Invoice duplicated as '.$copy->number.'.');
    }

    public function markSent(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('update', $invoice);

        if ($invoice->isDraft()) {
            $invoice->update([
                'status' => InvoiceStatus::Sent->value,
                'sent_at' => now(),
            ]);
        }

        return back()->with('success', 'Invoice marked as sent.');
    }

    public function cancel(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('cancel', $invoice);

        $data = $request->validate([
            'cancel_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice->update([
            'status' => InvoiceStatus::Cancelled->value,
            'cancelled_at' => now(),
            'cancel_reason' => $data['cancel_reason'] ?? null,
        ]);

        return back()->with('success', 'Invoice cancelled.');
    }

    public function sendEmail(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('view', $invoice);

        if ($invoice->isCancelled()) {
            return back()->with('error', 'Cancelled invoices cannot be sent.');
        }

        if (! $invoice->company->notify_invoice_sent) {
            return back()->with('error', 'Invoice emails are disabled in Email settings.');
        }

        try {
            $this->delivery->send($invoice);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        if ($invoice->isDraft()) {
            $invoice->update([
                'status' => InvoiceStatus::Sent->value,
                'sent_at' => now(),
            ]);
        }

        return back()->with('success', 'Invoice emailed to '.$invoice->customer->email.'.');
    }

    public function pdf(Invoice $invoice): HttpResponse
    {
        Gate::authorize('view', $invoice);

        return $this->pdf->make($invoice)->download($this->pdf->filename($invoice));
    }

    /**
     * Public, signature-protected invoice view. No authentication required.
     */
    public function public(Invoice $invoice): Response
    {
        $invoice->load(['customer', 'items', 'company']);
        $invoice->markViewed();
        $invoice->refresh();

        return Inertia::render('Invoices/Public', [
            'invoice' => $this->presentDetail($invoice),
            'company' => $invoice->company->only([
                'name', 'email', 'phone', 'address',
                'payment_instructions', 'invoice_footer',
            ]),
            'pdfUrl' => $this->delivery->publicPdfUrl($invoice),
        ]);
    }

    public function publicPdf(Invoice $invoice): HttpResponse
    {
        return $this->pdf->make($invoice)->download($this->pdf->filename($invoice));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyStatusFilter(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '') {
            return;
        }

        if ($status === InvoiceStatus::Overdue->value) {
            $query->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value])
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereColumn('amount_paid', '<', 'total');

            return;
        }

        if ($status === InvoiceStatus::Paid->value) {
            $query->whereColumn('amount_paid', '>=', 'total')->where('total', '>', 0);

            return;
        }

        if ($status === InvoiceStatus::PartiallyPaid->value) {
            $query->where('amount_paid', '>', 0)->whereColumn('amount_paid', '<', 'total');

            return;
        }

        if (in_array($status, InvoiceStatus::stored(), true)) {
            $query->where('status', $status);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Invoice $invoice, Company $company): array
    {
        $status = $invoice->displayStatus();

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'customer_name' => $invoice->customer?->name,
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_class' => $status->badgeClasses(),
            'total' => (int) $invoice->total,
            'total_display' => Money::of((int) $invoice->total, $invoice->currency)->format(),
            'balance_display' => Money::of($invoice->balance(), $invoice->currency)->format(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(Invoice $invoice): array
    {
        $status = $invoice->displayStatus();
        $currency = $invoice->currency;

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_class' => $status->badgeClasses(),
            'issue_date' => $invoice->issue_date->toDateString(),
            'due_date' => $invoice->due_date->toDateString(),
            'currency' => $currency,
            'tax_inclusive' => (bool) $invoice->tax_inclusive,
            'discount_type' => $invoice->discount_type,
            'discount_value' => $invoice->discount_value,
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
            'subtotal' => (int) $invoice->subtotal,
            'discount_total' => (int) $invoice->discount_total,
            'tax_total' => (int) $invoice->tax_total,
            'total' => (int) $invoice->total,
            'amount_paid' => (int) $invoice->amount_paid,
            'credit_total' => (int) $invoice->credit_total,
            'balance' => $invoice->balance(),
            'formatted' => [
                'subtotal' => Money::of((int) $invoice->subtotal, $currency)->format(),
                'discount' => Money::of((int) $invoice->discount_total, $currency)->format(),
                'tax' => Money::of((int) $invoice->tax_total, $currency)->format(),
                'total' => Money::of((int) $invoice->total, $currency)->format(),
                'paid' => Money::of((int) $invoice->amount_paid, $currency)->format(),
                'credit' => Money::of((int) $invoice->credit_total, $currency)->format(),
                'balance' => Money::of($invoice->balance(), $currency)->format(),
            ],
            'customer' => [
                'id' => $invoice->customer->id,
                'name' => $invoice->customer->name,
                'company_name' => $invoice->customer->company_name,
                'email' => $invoice->customer->email,
                'billing_address' => $invoice->customer->billing_address,
            ],
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => Money::of((int) $item->unit_price, $currency)->format(),
                'tax_rate' => $item->tax_rate !== null ? (float) $item->tax_rate : null,
                'line_total' => Money::of((int) $item->line_total, $currency)->format(),
            ])->values(),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function productOptions(Company $company)
    {
        return Product::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'unit_price', 'tax_rate'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit_price' => Money::of((int) $product->unit_price, $company->currency)->toDecimal(),
                'tax_rate' => $product->tax_rate !== null ? (float) $product->tax_rate : null,
            ])
            ->values();
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

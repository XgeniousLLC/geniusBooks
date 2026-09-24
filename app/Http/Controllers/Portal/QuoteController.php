<?php

namespace App\Http\Controllers\Portal;

use App\Enums\QuoteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreQuoteRequest;
use App\Http\Requests\Portal\UpdateQuoteRequest;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quote;
use App\Services\DocumentNumberService;
use App\Services\Invoicing\QuoteService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly DocumentNumberService $numbers,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Quote::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'issue_date',
            'allowedSorts' => ['number', 'issue_date', 'total'],
            'filters' => ['status', 'customer_id'],
        ]);

        $query = Quote::query()->with('customer:id,name');

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term));
            });
        }

        if (! empty($list->filters['status'])) {
            $query->where('status', $list->filters['status']);
        }

        if (! empty($list->filters['customer_id'])) {
            $query->where('customer_id', $list->filters['customer_id']);
        }

        $list->apply($query, []);

        return Inertia::render('Quotes/Index', [
            'quotes' => $list->paginate($query)->through(fn (Quote $quote) => $this->presentRow($quote)),
            'filters' => $list->meta(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => collect(QuoteStatus::cases())
                ->map(fn (QuoteStatus $status) => ['value' => $status->value, 'label' => $status->label()])
                ->values(),
            'can' => ['create' => Gate::allows('create', Quote::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Quote::class);

        return Inertia::render('Quotes/Form', [
            'quote' => null,
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'products' => $this->productOptions($this->company()),
            'currency' => $this->company()->currency,
            'taxInclusive' => (bool) $this->company()->tax_inclusive,
            'nextNumber' => $this->numbers->preview($this->company(), 'quote'),
            'defaults' => [
                'issue_date' => now()->toDateString(),
                'valid_until' => now()->addDays(30)->toDateString(),
            ],
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $quote = $this->quotes->create($this->company(), $request->validated());

        return redirect()->route('portal.quotes.show', $quote)->with('success', 'Quote created.');
    }

    public function show(Quote $quote): Response
    {
        Gate::authorize('view', $quote);
        $quote->load(['customer', 'items', 'convertedInvoice:id,number']);

        return Inertia::render('Quotes/Show', [
            'quote' => $this->presentDetail($quote),
            'can' => [
                'update' => Gate::allows('update', $quote),
                'delete' => Gate::allows('delete', $quote),
                'convert' => Gate::allows('convert', $quote),
            ],
        ]);
    }

    public function edit(Quote $quote): Response|RedirectResponse
    {
        Gate::authorize('view', $quote);

        if (! $quote->isEditable()) {
            return redirect()->route('portal.quotes.show', $quote)
                ->with('error', 'Only draft or sent quotes can be edited.');
        }

        Gate::authorize('update', $quote);
        $quote->load('items');

        return Inertia::render('Quotes/Form', [
            'quote' => [
                'id' => $quote->id,
                'number' => $quote->number,
                'customer_id' => $quote->customer_id,
                'issue_date' => $quote->issue_date->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
                'discount_type' => $quote->discount_type,
                'discount_value' => $quote->discount_value,
                'notes' => $quote->notes,
                'terms' => $quote->terms,
                'items' => $quote->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => Money::of((int) $item->unit_price, $quote->currency)->toDecimal(),
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'tax_rate' => $item->tax_rate,
                ])->values(),
            ],
            'customers' => Customer::query()->active()->orderBy('name')->get(['id', 'name']),
            'products' => $this->productOptions($this->company()),
            'currency' => $quote->currency,
            'taxInclusive' => (bool) $quote->tax_inclusive,
            'nextNumber' => $quote->number,
            'defaults' => [
                'issue_date' => $quote->issue_date->toDateString(),
                'valid_until' => $quote->valid_until?->toDateString(),
            ],
        ]);
    }

    public function update(UpdateQuoteRequest $request, Quote $quote): RedirectResponse
    {
        $this->quotes->update($quote, $request->validated());

        return redirect()->route('portal.quotes.show', $quote)->with('success', 'Quote updated.');
    }

    public function destroy(Quote $quote): RedirectResponse
    {
        Gate::authorize('delete', $quote);

        $quote->delete();

        return redirect()->route('portal.quotes.index')->with('success', 'Quote deleted.');
    }

    public function markSent(Quote $quote): RedirectResponse
    {
        Gate::authorize('view', $quote);

        if ($quote->status === QuoteStatus::Draft->value) {
            $quote->update(['status' => QuoteStatus::Sent->value, 'sent_at' => now()]);
        }

        return back()->with('success', 'Quote marked as sent.');
    }

    public function accept(Quote $quote): RedirectResponse
    {
        Gate::authorize('view', $quote);

        $quote->update(['status' => QuoteStatus::Accepted->value]);

        return back()->with('success', 'Quote marked as accepted.');
    }

    public function decline(Quote $quote): RedirectResponse
    {
        Gate::authorize('view', $quote);

        $quote->update(['status' => QuoteStatus::Declined->value]);

        return back()->with('success', 'Quote marked as declined.');
    }

    public function convert(Quote $quote): RedirectResponse
    {
        Gate::authorize('convert', $quote);

        $invoice = $this->quotes->convert($quote);

        return redirect()->route('portal.invoices.show', $invoice)
            ->with('success', 'Quote converted to invoice '.$invoice->number.'.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentRow(Quote $quote): array
    {
        $status = $quote->displayStatus();

        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'customer_name' => $quote->customer?->name,
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_class' => $status->badgeClasses(),
            'total_display' => Money::of((int) $quote->total, $quote->currency)->format(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentDetail(Quote $quote): array
    {
        $status = $quote->displayStatus();
        $currency = $quote->currency;

        return [
            'id' => $quote->id,
            'number' => $quote->number,
            'status' => $status->value,
            'status_label' => $status->label(),
            'status_class' => $status->badgeClasses(),
            'issue_date' => $quote->issue_date->toDateString(),
            'valid_until' => $quote->valid_until?->toDateString(),
            'currency' => $currency,
            'tax_inclusive' => (bool) $quote->tax_inclusive,
            'notes' => $quote->notes,
            'terms' => $quote->terms,
            'is_converted' => $quote->isConverted(),
            'converted_invoice' => $quote->convertedInvoice?->only(['id', 'number']),
            'customer' => [
                'id' => $quote->customer->id,
                'name' => $quote->customer->name,
                'company_name' => $quote->customer->company_name,
                'email' => $quote->customer->email,
                'billing_address' => $quote->customer->billing_address,
            ],
            'items' => $quote->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => Money::of((int) $item->unit_price, $currency)->format(),
                'tax_rate' => $item->tax_rate !== null ? (float) $item->tax_rate : null,
                'line_total' => Money::of((int) $item->line_total, $currency)->format(),
            ])->values(),
            'formatted' => [
                'subtotal' => Money::of((int) $quote->subtotal, $currency)->format(),
                'discount' => Money::of((int) $quote->discount_total, $currency)->format(),
                'tax' => Money::of((int) $quote->tax_total, $currency)->format(),
                'total' => Money::of((int) $quote->total, $currency)->format(),
            ],
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

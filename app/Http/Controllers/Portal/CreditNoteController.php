<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreCreditNoteRequest;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Accounting\CreditNoteService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CreditNoteController extends Controller
{
    public function __construct(private readonly CreditNoteService $creditNotes) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CreditNote::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'issue_date',
            'allowedSorts' => ['issue_date', 'amount', 'number'],
            'filters' => ['customer_id'],
        ]);

        $query = CreditNote::query()->with(['customer:id,name', 'invoice:id,number']);

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('number', 'like', $term)
                    ->orWhereHas('customer', fn (Builder $customer) => $customer->where('name', 'like', $term));
            });
        }

        if (! empty($list->filters['customer_id'])) {
            $query->where('customer_id', $list->filters['customer_id']);
        }

        $list->apply($query, []);

        return Inertia::render('CreditNotes/Index', [
            'creditNotes' => $list->paginate($query)->through(fn (CreditNote $credit) => [
                'id' => $credit->id,
                'number' => $credit->number,
                'customer_name' => $credit->customer?->name,
                'invoice_number' => $credit->invoice?->number,
                'issue_date' => $credit->issue_date->toDateString(),
                'amount_display' => Money::of((int) $credit->amount, $this->company()->currency)->format(),
                'status' => $credit->status,
            ]),
            'filters' => $list->meta(),
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreCreditNoteRequest $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('create', CreditNote::class);

        $data = $request->validated();

        $this->creditNotes->issue($this->company(), [
            'invoice_id' => $invoice->id,
            'issue_date' => $data['issue_date'],
            'amount' => Money::fromDecimal((string) $data['amount'], $invoice->currency)->amount(),
            'reason' => $data['reason'] ?? null,
            'bank_account_id' => $data['bank_account_id'] ?? null,
        ]);

        return back()->with('success', 'Credit note issued.');
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

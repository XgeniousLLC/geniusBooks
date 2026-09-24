<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreExpenseRequest;
use App\Http\Requests\Portal\UpdateExpenseRequest;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Vendor;
use App\Services\Accounting\ExpenseService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(private readonly ExpenseService $expenses) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Expense::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'date',
            'allowedSorts' => ['date', 'amount', 'created_at'],
            'filters' => ['expense_category_id', 'vendor_id', 'bank_account_id', 'from', 'to'],
        ]);

        $company = $this->company();
        $query = Expense::query()
            ->notVoided()
            ->with(['category:id,name', 'vendor:id,name']);

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where(function (Builder $inner) use ($term) {
                $inner->where('description', 'like', $term)->orWhere('reference', 'like', $term);
            });
        }

        foreach (['expense_category_id', 'vendor_id', 'bank_account_id'] as $filter) {
            if (! empty($list->filters[$filter])) {
                $query->where($filter, $list->filters[$filter]);
            }
        }

        if (! empty($list->filters['from'])) {
            $query->whereDate('date', '>=', $list->filters['from']);
        }

        if (! empty($list->filters['to'])) {
            $query->whereDate('date', '<=', $list->filters['to']);
        }

        $list->apply($query, []);
        $totalMinor = (int) $query->sum('amount');

        return Inertia::render('Expenses/Index', [
            'expenses' => $list->paginate($query)->through(fn (Expense $expense) => [
                'id' => $expense->id,
                'date' => $expense->date->toDateString(),
                'description' => $expense->description,
                'category' => $expense->category?->name,
                'vendor' => $expense->vendor?->name,
                'amount_display' => Money::of((int) $expense->amount, $expense->currency)->format(),
                'has_attachment' => $expense->hasAttachment(),
                'is_recurring' => $expense->is_recurring,
            ]),
            'filters' => $list->meta(),
            'categories' => ExpenseCategory::query()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->orderBy('name')->get(['id', 'name']),
            'accounts' => BankAccount::query()->orderBy('name')->get(['id', 'name']),
            'totals' => ['value' => Money::of($totalMinor, $company->currency)->format()],
            'can' => ['create' => Gate::allows('create', Expense::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Expense::class);

        return Inertia::render('Expenses/Form', $this->formProps(null));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $data = $this->prepare($request);

        $expense = $this->expenses->record($this->company(), $data);

        return redirect()->route('portal.expenses.show', $expense)->with('success', 'Expense recorded.');
    }

    public function show(Expense $expense): Response
    {
        Gate::authorize('view', $expense);
        $expense->load(['category:id,name', 'vendor:id,name', 'bankAccount:id,name']);

        return Inertia::render('Expenses/Show', [
            'expense' => [
                'id' => $expense->id,
                'date' => $expense->date->toDateString(),
                'description' => $expense->description,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'category' => $expense->category?->name,
                'vendor' => $expense->vendor?->name,
                'account' => $expense->bankAccount?->name,
                'amount_display' => Money::of((int) $expense->amount, $expense->currency)->format(),
                'tax_display' => Money::of((int) $expense->tax_amount, $expense->currency)->format(),
                'is_recurring' => $expense->is_recurring,
                'recurrence_interval' => $expense->recurrence_interval,
                'next_recurrence_on' => $expense->next_recurrence_on?->toDateString(),
                'is_voided' => $expense->isVoided(),
                'void_reason' => $expense->void_reason,
                'has_attachment' => $expense->hasAttachment(),
                'attachment_name' => $expense->attachment_name,
            ],
            'can' => [
                'update' => Gate::allows('update', $expense),
                'void' => Gate::allows('void', $expense),
            ],
        ]);
    }

    public function edit(Expense $expense): Response
    {
        Gate::authorize('update', $expense);

        return Inertia::render('Expenses/Form', $this->formProps($expense));
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->update($expense, $this->prepare($request, $expense));

        return redirect()->route('portal.expenses.show', $expense)->with('success', 'Expense updated.');
    }

    public function void(Request $request, Expense $expense): RedirectResponse
    {
        Gate::authorize('void', $expense);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->expenses->void($expense, $data['reason']);

        return back()->with('success', 'Expense voided.');
    }

    public function attachment(Expense $expense): StreamedResponse
    {
        Gate::authorize('view', $expense);
        abort_unless($expense->hasAttachment(), 404);

        return Storage::disk('local')->download($expense->attachment_path, $expense->attachment_name);
    }

    /**
     * @param  array<string, mixed>  $expense
     * @return array<string, mixed>
     */
    private function formProps(?Expense $expense): array
    {
        return [
            'expense' => $expense ? [
                'id' => $expense->id,
                'date' => $expense->date->toDateString(),
                'amount' => Money::of((int) $expense->amount, $expense->currency)->toDecimal(),
                'tax_amount' => $expense->tax_amount > 0
                    ? Money::of((int) $expense->tax_amount, $expense->currency)->toDecimal()
                    : '',
                'expense_category_id' => $expense->expense_category_id,
                'vendor_id' => $expense->vendor_id,
                'bank_account_id' => $expense->bank_account_id,
                'description' => $expense->description,
                'reference' => $expense->reference,
                'notes' => $expense->notes,
                'is_recurring' => $expense->is_recurring,
                'recurrence_interval' => $expense->recurrence_interval,
                'attachment_name' => $expense->attachment_name,
            ] : null,
            'categories' => ExpenseCategory::query()->active()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->active()->orderBy('name')->get(['id', 'name']),
            'accounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'intervals' => Expense::INTERVALS,
            'currency' => $this->company()->currency,
            'defaults' => ['date' => now()->toDateString()],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function prepare(Request $request, ?Expense $expense = null): array
    {
        $company = $this->company();
        $data = $request->validated();
        $currency = $company->currency;

        $data['amount'] = Money::fromDecimal((string) $data['amount'], $currency)->amount();
        $data['tax_amount'] = isset($data['tax_amount'])
            ? Money::fromDecimal((string) $data['tax_amount'], $currency)->amount()
            : 0;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $data['attachment_path'] = $file->store('expenses/'.$company->id, 'local');
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $file->getClientMimeType();
            $data['attachment_size'] = $file->getSize();

            if ($expense?->attachment_path) {
                Storage::disk('local')->delete($expense->attachment_path);
            }
        }

        return $data;
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

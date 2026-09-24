<?php

namespace App\Http\Controllers\Portal;

use App\Enums\LedgerAccountType;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Services\Accounting\LedgerPostingService;
use App\Support\CompanyContext;
use App\Support\ListQuery;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Transaction::class);

        $list = ListQuery::fromRequest($request, [
            'defaultSort' => 'occurred_on',
            'allowedSorts' => ['occurred_on', 'amount', 'created_at'],
            'filters' => ['type', 'direction', 'bank_account_id', 'ledger_account_id', 'from', 'to'],
        ]);

        $company = $this->company();
        $query = Transaction::query()->with(['bankAccount:id,name', 'ledgerAccount:id,name']);

        if ($list->search !== null) {
            $term = '%'.$list->search.'%';
            $query->where('description', 'like', $term);
        }

        foreach (['type', 'direction', 'bank_account_id', 'ledger_account_id'] as $filter) {
            if (! empty($list->filters[$filter])) {
                $query->where($filter, $list->filters[$filter]);
            }
        }

        if (! empty($list->filters['from'])) {
            $query->whereDate('occurred_on', '>=', $list->filters['from']);
        }

        if (! empty($list->filters['to'])) {
            $query->whereDate('occurred_on', '<=', $list->filters['to']);
        }

        $list->apply($query, []);

        $income = (int) Transaction::query()->where('type', TransactionType::Income->value)->where('direction', 'in')->sum('amount');
        $expense = (int) Transaction::query()->where('type', TransactionType::Expense->value)->where('direction', 'out')->sum('amount');

        return Inertia::render('Transactions/Index', [
            'transactions' => $list->paginate($query)->through(fn (Transaction $transaction) => $this->present($transaction)),
            'filters' => $list->meta(),
            'accounts' => BankAccount::query()->orderBy('name')->get(['id', 'name']),
            'ledgerAccounts' => LedgerAccount::query()->orderBy('code')->get(['id', 'code', 'name']),
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $type) => ['value' => $type->value, 'label' => $type->label()])->values(),
            'summary' => [
                'income' => Money::of($income, $company->currency)->format(),
                'expense' => Money::of($expense, $company->currency)->format(),
                'net' => Money::of($income - $expense, $company->currency)->format(),
            ],
            'can' => ['create' => Gate::allows('create', Transaction::class)],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Transaction::class);

        return Inertia::render('Transactions/Create', [
            'type' => $request->string('type', 'transfer')->toString(),
            'accounts' => BankAccount::query()->active()->orderBy('name')->get(['id', 'name']),
            'revenueAccounts' => LedgerAccount::query()->active()->where('type', LedgerAccountType::Revenue->value)->orderBy('code')->get(['id', 'code', 'name']),
            'expenseAccounts' => LedgerAccount::query()->active()->where('type', LedgerAccountType::Expense->value)->orderBy('code')->get(['id', 'code', 'name']),
            'currency' => $this->company()->currency,
            'defaults' => ['date' => now()->toDateString()],
        ]);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        Gate::authorize('create', Transaction::class);
        $company = $this->company();

        $data = $request->validate([
            'from_bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $company->id)],
            'to_bank_account_id' => ['required', 'different:from_bank_account_id', Rule::exists('bank_accounts', 'id')->where('company_id', $company->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $from = BankAccount::query()->findOrFail($data['from_bank_account_id']);
        $to = BankAccount::query()->findOrFail($data['to_bank_account_id']);
        $amount = Money::fromDecimal((string) $data['amount'], $from->currency)->amount();

        $this->ledger->transfer($company, $from, $to, $amount, $data['description'], $data['date']);

        return redirect()->route('portal.transactions.index')->with('success', 'Transfer recorded.');
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        Gate::authorize('create', Transaction::class);
        $company = $this->company();

        $data = $request->validate([
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $company->id)],
            'ledger_account_id' => ['nullable', Rule::exists('ledger_accounts', 'id')->where('company_id', $company->id)],
            'direction' => ['required', Rule::in([TransactionDirection::In->value, TransactionDirection::Out->value])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $account = ! empty($data['bank_account_id']) ? BankAccount::query()->find($data['bank_account_id']) : null;
        $ledgerAccount = ! empty($data['ledger_account_id']) ? LedgerAccount::query()->find($data['ledger_account_id']) : null;
        $currency = $account?->currency ?? $company->currency;
        $amount = Money::fromDecimal((string) $data['amount'], $currency)->amount();

        $payload = [
            'bank_account_id' => $account?->id,
            'ledger_account_id' => $ledgerAccount?->id,
            'type' => TransactionType::Adjustment->value,
            'amount' => $amount,
            'currency' => $currency,
            'description' => $data['description'],
            'occurred_on' => $data['date'],
            'reason' => $data['reason'] ?? null,
        ];

        if ($data['direction'] === TransactionDirection::In->value) {
            $this->ledger->post($company, $payload + ['direction' => TransactionDirection::In->value]);
        } else {
            $this->ledger->post($company, $payload + ['direction' => TransactionDirection::Out->value]);
        }

        return redirect()->route('portal.transactions.index')->with('success', 'Adjustment recorded.');
    }

    public function storeIncome(Request $request): RedirectResponse
    {
        Gate::authorize('create', Transaction::class);
        $company = $this->company();

        $data = $request->validate([
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $company->id)],
            'ledger_account_id' => ['required', Rule::exists('ledger_accounts', 'id')->where('company_id', $company->id)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $account = BankAccount::query()->findOrFail($data['bank_account_id']);
        $ledgerAccount = LedgerAccount::query()->findOrFail($data['ledger_account_id']);
        $amount = Money::fromDecimal((string) $data['amount'], $account->currency)->amount();

        $this->ledger->in(
            $company,
            $account,
            $amount,
            TransactionType::Income->value,
            $data['description'],
            $data['date'],
            null,
            null,
            $ledgerAccount,
        );

        return redirect()->route('portal.transactions.index')->with('success', 'Income recorded.');
    }

    public function reverse(Request $request, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('reverse', $transaction);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->ledger->reverse($transaction, $data['reason']);

        return back()->with('success', 'Transaction reversed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'occurred_on' => $transaction->occurred_on->toDateString(),
            'description' => $transaction->description,
            'type' => $transaction->type,
            'type_label' => TransactionType::tryFrom($transaction->type)?->label() ?? $transaction->type,
            'direction' => $transaction->direction,
            'amount_display' => Money::of((int) $transaction->amount, $transaction->currency)->format(),
            'bank_account' => $transaction->bankAccount?->name,
            'ledger_account' => $transaction->ledgerAccount?->name,
            'source_url' => $this->sourceUrl($transaction),
            'can_reverse' => Gate::allows('reverse', $transaction),
        ];
    }

    private function sourceUrl(Transaction $transaction): ?string
    {
        if (! $transaction->source_type || ! $transaction->source_id) {
            return null;
        }

        return match ($transaction->source_type) {
            'App\\Models\\Payment' => route('portal.payments.show', $transaction->source_id),
            'App\\Models\\Expense' => route('portal.expenses.show', $transaction->source_id),
            'App\\Models\\Invoice' => route('portal.invoices.show', $transaction->source_id),
            default => null,
        };
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

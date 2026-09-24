<?php

namespace App\Http\Controllers\Portal;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreBankAccountRequest;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\Accounting\LedgerPostingService;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BankAccountController extends Controller
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', BankAccount::class);

        $company = $this->company();

        $accounts = BankAccount::query()
            ->orderBy('name')
            ->get()
            ->map(fn (BankAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
                'balance' => $account->balance(),
                'balance_display' => Money::of($account->balance(), $account->currency)->format(),
            ])
            ->values();

        return Inertia::render('Accounts/Index', [
            'accounts' => $accounts,
            'total_display' => Money::of(
                (int) $accounts->sum('balance'),
                $company->currency,
            )->format(),
            'can' => ['create' => Gate::allows('create', BankAccount::class)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', BankAccount::class);

        return Inertia::render('Accounts/Form', [
            'account' => null,
            'currency' => $this->company()->currency,
            'types' => BankAccount::TYPES,
        ]);
    }

    public function store(StoreBankAccountRequest $request): RedirectResponse
    {
        $company = $this->company();
        $data = $request->validated();

        $account = BankAccount::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'currency' => $company->currency,
            'opening_balance' => Money::fromDecimal((string) $data['opening_balance'], $company->currency)->amount(),
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('portal.accounts.show', $account)
            ->with('success', 'Account created.');
    }

    public function show(BankAccount $account): Response
    {
        Gate::authorize('view', $account);

        return Inertia::render('Accounts/Show', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'currency' => $account->currency,
                'is_active' => $account->is_active,
                'opening_balance_display' => Money::of((int) $account->opening_balance, $account->currency)->format(),
                'balance' => $account->balance(),
                'balance_display' => Money::of($account->balance(), $account->currency)->format(),
            ],
            'transactions' => $this->history($account),
            'can' => ['update' => Gate::allows('update', $account)],
        ]);
    }

    public function edit(BankAccount $account): Response
    {
        Gate::authorize('update', $account);

        return Inertia::render('Accounts/Form', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'type' => $account->type,
                'opening_balance' => Money::of((int) $account->opening_balance, $account->currency)->toDecimal(),
                'is_active' => $account->is_active,
            ],
            'currency' => $account->currency,
            'types' => BankAccount::TYPES,
        ]);
    }

    public function update(StoreBankAccountRequest $request, BankAccount $account): RedirectResponse
    {
        Gate::authorize('update', $account);

        $data = $request->validated();

        $account->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'opening_balance' => Money::fromDecimal((string) $data['opening_balance'], $account->currency)->amount(),
            'is_active' => $data['is_active'],
        ]);

        return redirect()
            ->route('portal.accounts.show', $account)
            ->with('success', 'Account updated.');
    }

    public function destroy(BankAccount $account): RedirectResponse
    {
        Gate::authorize('delete', $account);

        $account->delete();

        return redirect()->route('portal.accounts.index')->with('success', 'Account archived.');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function history(BankAccount $account)
    {
        $running = (int) $account->opening_balance;

        return Transaction::withoutCompanyScope()
            ->where('bank_account_id', $account->id)
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(function (Transaction $transaction) use (&$running, $account) {
                $running += $transaction->signedAmount();

                return [
                    'id' => $transaction->id,
                    'occurred_on' => $transaction->occurred_on->toDateString(),
                    'description' => $transaction->description,
                    'type' => TransactionType::tryFrom($transaction->type)?->label() ?? $transaction->type,
                    'direction' => $transaction->direction,
                    'amount_display' => Money::of((int) $transaction->amount, $account->currency)->format(),
                    'signed' => $transaction->direction === 'in' ? (int) $transaction->amount : -(int) $transaction->amount,
                    'balance_display' => Money::of($running, $account->currency)->format(),
                ];
            })
            ->reverse()
            ->values();
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

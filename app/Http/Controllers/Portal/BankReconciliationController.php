<?php

namespace App\Http\Controllers\Portal;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Services\Banking\BankReconciliationService;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class BankReconciliationController extends Controller
{
    public function __construct(private readonly BankReconciliationService $reconciliation) {}

    public function index(BankAccount $account): Response
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureAccount($account);
        $company = $this->company();

        $lines = BankStatementLine::query()
            ->where('bank_account_id', $account->id)
            ->with('matchedTransaction:id,description,occurred_on,amount,direction,currency')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $matchedTransactionIds = BankStatementLine::query()
            ->where('bank_account_id', $account->id)
            ->whereNotNull('matched_transaction_id')
            ->pluck('matched_transaction_id')
            ->all();

        $candidates = Transaction::query()
            ->where('bank_account_id', $account->id)
            ->whereNotIn('id', $matchedTransactionIds ?: [0])
            ->orderByDesc('occurred_on')
            ->limit(200)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'label' => $transaction->occurred_on->toDateString().' · '.$transaction->description.' · '
                    .Money::of((int) $transaction->amount, $transaction->currency)->format(),
            ])
            ->values();

        return Inertia::render('Reconciliation/Index', [
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'balance_display' => Money::of($account->balance(), $account->currency)->format(),
            ],
            'lines' => $lines->map(fn (BankStatementLine $line) => $this->present($line))->values(),
            'candidates' => $candidates,
            'ledgerAccounts' => LedgerAccount::query()->active()->orderBy('code')->get(['id', 'code', 'name']),
            'summary' => [
                'total' => $lines->count(),
                'matched' => $lines->whereNotNull('matched_transaction_id')->count(),
                'reconciled' => $lines->whereNotNull('reconciled_at')->count(),
                'unmatched' => $lines->whereNull('matched_transaction_id')->count(),
            ],
            'can' => ['manage' => Gate::allows(Permission::ManageFinances)],
            'currency' => $company->currency,
        ]);
    }

    public function import(Request $request, BankAccount $account): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureAccount($account);

        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            $result = $this->reconciliation->import($this->company(), $account, $request->file('file')->getRealPath());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if ($result['errors'] !== []) {
            return back()->with('importErrors', $result['errors']);
        }

        return back()->with('success', $result['imported'].' statement line(s) imported.');
    }

    public function autoMatch(BankAccount $account): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureAccount($account);

        $count = $this->reconciliation->autoMatch($this->company(), $account);

        return back()->with('success', $count.' line(s) matched automatically.');
    }

    public function match(Request $request, BankAccount $account, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureLine($account, $line);

        $data = $request->validate(['transaction_id' => ['required', 'integer']]);
        $transaction = Transaction::query()->findOrFail($data['transaction_id']);

        try {
            $this->reconciliation->match($line, $transaction);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Line matched.');
    }

    public function unmatch(BankAccount $account, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureLine($account, $line);

        $this->reconciliation->unmatch($line);

        return back()->with('success', 'Line unmatched.');
    }

    public function createTransaction(Request $request, BankAccount $account, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureLine($account, $line);

        $data = $request->validate([
            'ledger_account_id' => ['nullable', 'integer'],
        ]);

        try {
            $this->reconciliation->createTransaction($line, $data['ledger_account_id'] ?? null);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Transaction created and reconciled.');
    }

    public function reconcile(Request $request, BankAccount $account, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize(Permission::ManageFinances);
        $this->ensureLine($account, $line);

        $reconciled = $request->boolean('reconciled');

        try {
            $this->reconciliation->setReconciled($line, $reconciled);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $reconciled ? 'Line reconciled.' : 'Reconciliation cleared.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(BankStatementLine $line): array
    {
        $currency = $line->currency;
        $amount = (int) $line->amount;

        return [
            'id' => $line->id,
            'date' => $line->date->toDateString(),
            'description' => $line->description,
            'reference' => $line->reference,
            'amount_display' => Money::of(abs($amount), $currency)->format(),
            'direction' => $amount >= 0 ? 'in' : 'out',
            'is_matched' => $line->isMatched(),
            'is_reconciled' => $line->isReconciled(),
            'matched_transaction' => $line->matchedTransaction ? [
                'id' => $line->matchedTransaction->id,
                'description' => $line->matchedTransaction->description,
                'occurred_on' => $line->matchedTransaction->occurred_on->toDateString(),
            ] : null,
        ];
    }

    private function ensureAccount(BankAccount $account): void
    {
        abort_unless($account->company_id === $this->company()->id, 404);
    }

    private function ensureLine(BankAccount $account, BankStatementLine $line): void
    {
        $this->ensureAccount($account);
        abort_unless($line->bank_account_id === $account->id, 404);
    }

    private function company(): Company
    {
        return Company::findOrFail(app(CompanyContext::class)->id());
    }
}

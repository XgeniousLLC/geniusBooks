<?php

namespace App\Services\Banking;

use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Company;
use App\Models\Transaction;
use App\Services\Accounting\LedgerPostingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Imports bank statement lines and reconciles them against ledger
 * transactions for the account.
 */
class BankReconciliationService
{
    private const COLUMNS = ['date', 'description', 'amount', 'reference'];

    public function __construct(private readonly LedgerPostingService $ledger) {}

    /**
     * @return array{imported: int, errors: list<array{row: int, message: string}>}
     */
    public function import(Company $company, BankAccount $account, string $path): array
    {
        $rows = $this->read($path);

        $errors = [];
        $valid = [];

        foreach ($rows as $row) {
            $line = $row['__line'];
            $date = trim((string) ($row['date'] ?? ''));
            $description = trim((string) ($row['description'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? ''));

            if ($date === '' || strtotime($date) === false) {
                $errors[] = ['row' => $line, 'message' => 'date is missing or invalid'];

                continue;
            }

            if ($description === '') {
                $errors[] = ['row' => $line, 'message' => 'description is required'];

                continue;
            }

            if (! is_numeric($amount)) {
                $errors[] = ['row' => $line, 'message' => 'amount must be a number'];

                continue;
            }

            $valid[] = [
                'date' => date('Y-m-d', strtotime($date)),
                'description' => $description,
                'reference' => trim((string) ($row['reference'] ?? '')) ?: null,
                'amount' => $this->toMinor($amount, $account->currency),
            ];
        }

        if ($errors !== []) {
            return ['imported' => 0, 'errors' => $errors];
        }

        $batch = (string) Str::uuid();

        DB::transaction(function () use ($company, $account, $valid, $batch) {
            foreach ($valid as $line) {
                BankStatementLine::create([
                    'company_id' => $company->id,
                    'bank_account_id' => $account->id,
                    'import_batch' => $batch,
                    'date' => $line['date'],
                    'description' => $line['description'],
                    'reference' => $line['reference'],
                    'amount' => $line['amount'],
                    'currency' => $account->currency,
                ]);
            }
        });

        return ['imported' => count($valid), 'errors' => []];
    }

    /**
     * Auto-match unmatched statement lines to ledger transactions of the same
     * amount within a small date window.
     */
    public function autoMatch(Company $company, BankAccount $account, int $daysWindow = 7): int
    {
        $matchedTransactionIds = BankStatementLine::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereNotNull('matched_transaction_id')
            ->pluck('matched_transaction_id')
            ->all();

        $transactions = Transaction::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('bank_account_id', $account->id)
            ->whereNotIn('id', $matchedTransactionIds ?: [0])
            ->get();

        $lines = BankStatementLine::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('bank_account_id', $account->id)
            ->whereNull('matched_transaction_id')
            ->orderBy('date')
            ->get();

        $matched = 0;

        foreach ($lines as $line) {
            $candidate = $transactions
                ->filter(fn (Transaction $transaction) => $transaction->signedAmount() === (int) $line->amount)
                ->filter(fn (Transaction $transaction) => abs($transaction->occurred_on->diffInDays($line->date)) <= $daysWindow)
                ->sortBy(fn (Transaction $transaction) => abs($transaction->occurred_on->diffInDays($line->date)))
                ->first();

            if ($candidate) {
                $line->update(['matched_transaction_id' => $candidate->id, 'reconciled_at' => null]);
                $transactions = $transactions->reject(fn (Transaction $t) => $t->id === $candidate->id);
                $matched++;
            }
        }

        return $matched;
    }

    public function match(BankStatementLine $line, Transaction $transaction): void
    {
        if ((int) $transaction->bank_account_id !== (int) $line->bank_account_id) {
            throw new InvalidArgumentException('The transaction belongs to a different account.');
        }

        $line->update(['matched_transaction_id' => $transaction->id, 'reconciled_at' => null]);
    }

    public function unmatch(BankStatementLine $line): void
    {
        $line->update(['matched_transaction_id' => null, 'reconciled_at' => null]);
    }

    /**
     * Create a ledger entry from an unmatched statement line, then match it.
     */
    public function createTransaction(BankStatementLine $line, ?int $ledgerAccountId = null): void
    {
        $company = $line->company;
        $account = $line->bankAccount;
        $amount = abs((int) $line->amount);

        if ($amount === 0) {
            throw new InvalidArgumentException('This statement line has no amount.');
        }

        DB::transaction(function () use ($company, $account, $line, $amount, $ledgerAccountId) {
            $ledgerAccount = $ledgerAccountId
                ? \App\Models\LedgerAccount::withoutCompanyScope()
                    ->where('company_id', $company->id)->find($ledgerAccountId)
                : null;

            $transaction = $line->amount >= 0
                ? $this->ledger->in($company, $account, $amount, \App\Enums\TransactionType::Adjustment->value, $line->description, $line->date, null, 'Bank reconciliation', $ledgerAccount)
                : $this->ledger->out($company, $account, $amount, \App\Enums\TransactionType::Adjustment->value, $line->description, $line->date, null, 'Bank reconciliation', $ledgerAccount);

            $line->update([
                'matched_transaction_id' => $transaction->id,
                'reconciled_at' => now(),
            ]);
        });
    }

    public function setReconciled(BankStatementLine $line, bool $reconciled): void
    {
        if ($reconciled && ! $line->isMatched()) {
            throw new InvalidArgumentException('Match the line to a transaction before reconciling.');
        }

        $line->update(['reconciled_at' => $reconciled ? now() : null]);
    }

    private function toMinor(string $amount, string $currency): int
    {
        $negative = str_starts_with(trim($amount), '-');
        $minor = Money::fromDecimal(ltrim(trim($amount), '-'), $currency)->amount();

        return $negative ? -$minor : $minor;
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded file.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === [null]) {
                throw new InvalidArgumentException('The file is empty.');
            }

            $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

            foreach (['date', 'description', 'amount'] as $required) {
                if (! in_array($required, $header, true)) {
                    throw new InvalidArgumentException("The file must include a \"{$required}\" column.");
                }
            }

            $rows = [];
            $line = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $line++;

                if (count(array_filter($data, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }

                $data = array_slice(array_pad($data, count($header), null), 0, count($header));
                $row = array_combine($header, $data);
                $row['__line'] = $line;

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }
}

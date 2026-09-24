<?php

namespace App\Services\Accounting;

use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The single entry point for all money movements. Nothing else writes
 * transactions or mutates balances directly.
 */
class LedgerPostingService
{
    public function post(Company $company, array $data): Transaction
    {
        $source = $data['source'] ?? null;

        return Transaction::create([
            'company_id' => $company->id,
            'bank_account_id' => $data['bank_account_id'] ?? null,
            'ledger_account_id' => $data['ledger_account_id'] ?? null,
            'type' => $data['type'],
            'direction' => $data['direction'],
            'amount' => (int) $data['amount'],
            'currency' => $data['currency'] ?? $company->currency,
            'description' => $data['description'],
            'occurred_on' => $data['occurred_on'],
            'source_type' => $source instanceof Model ? $source->getMorphClass() : null,
            'source_id' => $source instanceof Model ? $source->getKey() : null,
            'transfer_group' => $data['transfer_group'] ?? null,
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function in(
        Company $company,
        ?BankAccount $account,
        int $amount,
        string $type,
        string $description,
        CarbonInterface|string $date,
        ?Model $source = null,
        ?string $reason = null,
        ?LedgerAccount $ledgerAccount = null,
    ): Transaction {
        return $this->post($company, [
            'bank_account_id' => $account?->id,
            'ledger_account_id' => $ledgerAccount?->id,
            'type' => $type,
            'direction' => TransactionDirection::In->value,
            'amount' => $amount,
            'currency' => $account?->currency ?? $company->currency,
            'description' => $description,
            'occurred_on' => $date,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    public function out(
        Company $company,
        ?BankAccount $account,
        int $amount,
        string $type,
        string $description,
        CarbonInterface|string $date,
        ?Model $source = null,
        ?string $reason = null,
        ?LedgerAccount $ledgerAccount = null,
    ): Transaction {
        return $this->post($company, [
            'bank_account_id' => $account?->id,
            'ledger_account_id' => $ledgerAccount?->id,
            'type' => $type,
            'direction' => TransactionDirection::Out->value,
            'amount' => $amount,
            'currency' => $account?->currency ?? $company->currency,
            'description' => $description,
            'occurred_on' => $date,
            'source' => $source,
            'reason' => $reason,
        ]);
    }

    /**
     * @return array{out: Transaction, in: Transaction}
     */
    public function transfer(
        Company $company,
        BankAccount $from,
        BankAccount $to,
        int $amount,
        string $description,
        CarbonInterface|string $date,
    ): array {
        $group = (string) Str::uuid();

        $out = $this->post($company, [
            'bank_account_id' => $from->id,
            'type' => TransactionType::Transfer->value,
            'direction' => TransactionDirection::Out->value,
            'amount' => $amount,
            'currency' => $from->currency,
            'description' => $description,
            'occurred_on' => $date,
            'transfer_group' => $group,
        ]);

        $in = $this->post($company, [
            'bank_account_id' => $to->id,
            'type' => TransactionType::Transfer->value,
            'direction' => TransactionDirection::In->value,
            'amount' => $amount,
            'currency' => $to->currency,
            'description' => $description,
            'occurred_on' => $date,
            'transfer_group' => $group,
        ]);

        return ['out' => $out, 'in' => $in];
    }

    /**
     * Post a counter-entry that reverses the effect of an existing transaction.
     */
    public function reverse(Transaction $transaction, string $reason): Transaction
    {
        return $this->post($transaction->company, [
            'bank_account_id' => $transaction->bank_account_id,
            'ledger_account_id' => $transaction->ledger_account_id,
            'type' => TransactionType::Adjustment->value,
            'direction' => $transaction->direction === TransactionDirection::In->value
                ? TransactionDirection::Out->value
                : TransactionDirection::In->value,
            'amount' => (int) $transaction->amount,
            'currency' => $transaction->currency,
            'description' => 'Reversal: '.$transaction->description,
            'occurred_on' => now(),
            'source' => $transaction->source,
            'reason' => $reason,
        ]);
    }
}

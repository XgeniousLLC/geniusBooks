<?php

namespace App\Services\Accounting;

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Records expenses, posts them to the ledger, and handles recurring templates.
 */
class ExpenseService
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Company $company, array $data): Expense
    {
        return DB::transaction(function () use ($company, $data) {
            $account = $this->account($company, $data['bank_account_id'] ?? null);

            $expense = Expense::create([
                'vendor_id' => $data['vendor_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'bank_account_id' => $account?->id,
                'date' => $data['date'],
                'amount' => $data['amount'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'currency' => $account?->currency ?? $company->currency,
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'attachment_name' => $data['attachment_name'] ?? null,
                'attachment_mime' => $data['attachment_mime'] ?? null,
                'attachment_size' => $data['attachment_size'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_interval' => $data['recurrence_interval'] ?? null,
                'next_recurrence_on' => ($data['is_recurring'] ?? false)
                    ? $this->advance($data['date'], $data['recurrence_interval'] ?? 'monthly')
                    : null,
            ]);

            $this->post($company, $expense, $account);

            return $expense->fresh(['transaction']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {
            $company = $expense->company;
            $account = $this->account($company, $data['bank_account_id'] ?? null);

            if ($expense->transaction) {
                $this->ledger->reverse($expense->transaction, 'Expense updated');
            }

            $expense->update([
                'vendor_id' => $data['vendor_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'] ?? null,
                'bank_account_id' => $account?->id,
                'date' => $data['date'],
                'amount' => $data['amount'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'description' => $data['description'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_recurring' => $data['is_recurring'] ?? false,
                'recurrence_interval' => $data['recurrence_interval'] ?? null,
                'next_recurrence_on' => ($data['is_recurring'] ?? false)
                    ? ($expense->next_recurrence_on ?? $this->advance($data['date'], $data['recurrence_interval'] ?? 'monthly'))
                    : null,
            ]);

            $this->post($company, $expense, $account);

            return $expense->fresh(['transaction']);
        });
    }

    public function void(Expense $expense, string $reason): void
    {
        DB::transaction(function () use ($expense, $reason) {
            if ($expense->isVoided()) {
                return;
            }

            if ($expense->transaction) {
                $this->ledger->reverse($expense->transaction, $reason);
            }

            $expense->void($reason);
        });
    }

    /**
     * Generate the next occurrence of a recurring expense template.
     */
    public function generateRecurring(Expense $template): ?Expense
    {
        if ($template->isVoided() || ! $template->is_recurring || ! $template->next_recurrence_on) {
            return null;
        }

        if ($template->next_recurrence_on->isAfter(now())) {
            return null;
        }

        return DB::transaction(function () use ($template) {
            $company = $template->company;
            $account = $template->bank_account_id
                ? BankAccount::withoutCompanyScope()->find($template->bank_account_id)
                : null;

            $child = Expense::create([
                'company_id' => $company->id,
                'vendor_id' => $template->vendor_id,
                'expense_category_id' => $template->expense_category_id,
                'bank_account_id' => $template->bank_account_id,
                'recurrence_parent_id' => $template->id,
                'date' => $template->next_recurrence_on->toDateString(),
                'amount' => $template->amount,
                'tax_amount' => $template->tax_amount,
                'currency' => $template->currency,
                'description' => $template->description,
                'reference' => $template->reference,
                'notes' => $template->notes,
                'is_recurring' => false,
            ]);

            $this->post($company, $child, $account);

            $template->update([
                'next_recurrence_on' => $this->advance($template->next_recurrence_on, $template->recurrence_interval ?? 'monthly'),
                'last_generated_at' => now(),
            ]);

            return $child->fresh(['transaction']);
        });
    }

    private function post(Company $company, Expense $expense, ?BankAccount $account): void
    {
        if (! $account) {
            $expense->update(['transaction_id' => null]);

            return;
        }

        $transaction = $this->ledger->out(
            $company,
            $account,
            (int) $expense->amount,
            TransactionType::Expense->value,
            $expense->description,
            $expense->date,
            $expense,
        );

        $expense->update(['transaction_id' => $transaction->id]);
    }

    public function advance(CarbonInterface|string $date, string $interval): \Carbon\CarbonInterface
    {
        $date = $date instanceof CarbonInterface ? $date : \Carbon\CarbonImmutable::parse($date);

        return match ($interval) {
            'weekly' => $date->addWeek(),
            'yearly' => $date->addYear(),
            default => $date->addMonth(),
        };
    }

    private function account(Company $company, ?int $accountId): ?BankAccount
    {
        if (! $accountId) {
            return null;
        }

        return BankAccount::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->findOrFail($accountId);
    }
}

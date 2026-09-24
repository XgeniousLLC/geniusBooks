<?php

namespace App\Services\Accounting;

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Issues credit notes against invoices and, when a refund account is supplied,
 * pays the credit back out of that account.
 */
class CreditNoteService
{
    public function __construct(
        private readonly LedgerPostingService $ledger,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function issue(Company $company, array $data): CreditNote
    {
        return DB::transaction(function () use ($company, $data) {
            $invoice = Invoice::withoutCompanyScope()
                ->where('company_id', $company->id)
                ->findOrFail($data['invoice_id']);

            $amount = (int) $data['amount'];

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'The credit amount must be greater than zero.']);
            }

            if ($amount > $invoice->balance()) {
                throw ValidationException::withMessages(['amount' => 'The credit cannot exceed the invoice balance.']);
            }

            $refundAccount = ! empty($data['bank_account_id'])
                ? BankAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['bank_account_id'])
                : null;

            $credit = CreditNote::create([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'number' => $this->numbers->next($company, 'credit_note'),
                'issue_date' => $data['issue_date'],
                'amount' => $amount,
                'reason' => $data['reason'] ?? null,
                'status' => $refundAccount ? CreditNote::STATUS_REFUNDED : CreditNote::STATUS_APPLIED,
                'bank_account_id' => $refundAccount?->id,
                'refunded_at' => $refundAccount ? now() : null,
            ]);

            if ($refundAccount) {
                $transaction = $this->ledger->out(
                    $company,
                    $refundAccount,
                    $amount,
                    TransactionType::Refund->value,
                    'Refund for '.$credit->number,
                    $data['issue_date'],
                    $credit,
                );

                $credit->update(['transaction_id' => $transaction->id]);
            }

            $this->recomputeInvoiceCredit($invoice);

            return $credit->fresh();
        });
    }

    public function recomputeInvoiceCredit(Invoice $invoice): void
    {
        $invoice->credit_total = (int) CreditNote::where('invoice_id', $invoice->id)->sum('amount');
        $invoice->save();
    }
}

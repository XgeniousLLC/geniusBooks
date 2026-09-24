<?php

namespace App\Services\Accounting;

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records payments, allocates them to invoices and posts the ledger entry.
 * Idempotency keys make repeated submissions safe.
 */
class PaymentService
{
    public function __construct(private readonly LedgerPostingService $ledger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Company $company, array $data): Payment
    {
        return DB::transaction(function () use ($company, $data) {
            $key = $data['idempotency_key'] ?? null;

            if ($key) {
                $existing = Payment::withoutCompanyScope()
                    ->where('company_id', $company->id)
                    ->where('idempotency_key', $key)
                    ->first();

                if ($existing) {
                    return $existing;
                }
            }

            $amount = (int) $data['amount'];
            $allocations = $data['allocations'] ?? [];
            $allocated = array_sum(array_map(fn ($a) => (int) $a['amount'], $allocations));

            if ($allocated > $amount) {
                throw ValidationException::withMessages([
                    'allocations' => 'Allocated amounts cannot exceed the payment amount.',
                ]);
            }

            $customer = $this->customer($company, $data['customer_id']);
            $account = $this->account($company, $data['bank_account_id']);

            $payment = Payment::create([
                'customer_id' => $customer->id,
                'bank_account_id' => $account->id,
                'date' => $data['date'],
                'amount' => $amount,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $key,
            ]);

            $transaction = $this->ledger->in(
                $company,
                $account,
                $amount,
                TransactionType::Payment->value,
                'Payment from '.$customer->name,
                $data['date'],
                $payment,
            );

            $payment->update(['transaction_id' => $transaction->id]);

            foreach ($allocations as $allocation) {
                $invoice = $this->invoice($company, $allocation['invoice_id']);
                $allocated = min((int) $allocation['amount'], $invoice->balance());

                if ($allocated <= 0) {
                    continue;
                }

                $payment->allocations()->create([
                    'invoice_id' => $invoice->id,
                    'amount' => $allocated,
                ]);

                $this->recomputeInvoice($invoice);
            }

            return $payment->fresh(['allocations']);
        });
    }

    public function void(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            if ($payment->transaction) {
                $this->ledger->reverse($payment->transaction, $reason);
            }

            $invoices = $payment->allocations()->with('invoice')->get();

            $payment->allocations()->delete();
            $payment->update([
                'voided_at' => now(),
                'void_reason' => $reason,
            ]);

            foreach ($invoices as $allocation) {
                if ($allocation->invoice) {
                    $this->recomputeInvoice($allocation->invoice);
                }
            }
        });
    }

    public function recomputeInvoice(Invoice $invoice): void
    {
        $invoice->amount_paid = (int) PaymentAllocation::where('invoice_id', $invoice->id)->sum('amount');
        $invoice->save();
    }

    /**
     * Unapplied customer credit (overpayments not allocated to invoices).
     */
    public function customerCredit(Customer $customer): int
    {
        return Payment::withoutCompanyScope()
            ->where('company_id', $customer->company_id)
            ->where('customer_id', $customer->id)
            ->whereNull('voided_at')
            ->get()
            ->sum(fn (Payment $payment) => $payment->unapplied());
    }

    private function customer(Company $company, int $id): Customer
    {
        return Customer::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->findOrFail($id);
    }

    private function account(Company $company, int $id): BankAccount
    {
        return BankAccount::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->findOrFail($id);
    }

    private function invoice(Company $company, int $id): Invoice
    {
        return Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->findOrFail($id);
    }
}

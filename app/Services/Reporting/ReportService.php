<?php

namespace App\Services\Reporting;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only financial reporting. All figures are derived from documents and the
 * ledger; nothing is stored.
 *
 * Reports return a normalised structure so a single view/export layer can render
 * them:
 *   [
 *     'key', 'title', 'period' => ['from','to'],
 *     'summary' => [ ['label','value','display','emphasis'] ],
 *     'sections' => [ ['heading','rows' => [ ['label','value','display'] ], 'total','total_display'] ],
 *   ]
 */
class ReportService
{
    private const OPEN_STATUSES = [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value];

    public function profitAndLoss(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $company->currency;

        $invoiced = (int) $this->invoices($company, $from, $to)->sum('total');
        $credits = (int) $this->creditNotes($company, $from, $to)->sum('amount');
        $directIncome = $this->directIncome($company, $from, $to);

        $revenueTotal = $invoiced - $credits + $directIncome;
        $expensesTotal = (int) $this->expenseQuery($company, $from, $to)->sum('amount');

        $expensesByCategory = $this->expenseQuery($company, $from, $to)
            ->load('category')
            ->groupBy(fn (Expense $expense) => $expense->category?->name ?? 'Uncategorised')
            ->map(fn (Collection $group) => (int) $group->sum('amount'));

        return $this->report(
            'profit-and-loss',
            'Profit & Loss',
            $from,
            $to,
            [
                $this->moneyRow('Invoiced sales', $invoiced, $currency),
                $this->moneyRow('Credit notes', -$credits, $currency),
                $this->moneyRow('Other income', $directIncome, $currency),
            ],
            $revenueTotal,
            $currency,
            $expensesByCategory,
            $expensesTotal,
            $currency,
        );
    }

    public function income(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $company->currency;

        $invoices = $this->invoices($company, $from, $to)->load('customer:id,name');
        $invoiced = (int) $invoices->sum('total');
        $direct = $this->directIncome($company, $from, $to);

        $byCustomer = $invoices->groupBy(fn (Invoice $invoice) => $invoice->customer?->name ?? 'Unknown')
            ->map(fn (Collection $group) => (int) $group->sum('total'));

        $byMonth = $invoices->groupBy(fn (Invoice $invoice) => $invoice->issue_date->format('Y-m'))
            ->map(fn (Collection $group) => (int) $group->sum('total'))
            ->sortKeys();

        $months = $byMonth->map(fn (int $amount, string $month) => $this->moneyRow($month, $amount, $currency))->values()->all();

        return [
            'key' => 'income',
            'title' => 'Income',
            'period' => $this->period($from, $to),
            'summary' => [
                $this->moneyStat('Invoiced income', $invoiced, $currency),
                $this->moneyStat('Direct income', $direct, $currency),
                $this->moneyStat('Total income', $invoiced + $direct, $currency, true),
            ],
            'sections' => [
                [
                    'heading' => 'Income by customer',
                    'rows' => $byCustomer->map(fn (int $amount, string $name) => $this->moneyRow($name, $amount, $currency))->values()->all(),
                    'total' => $invoiced,
                    'total_display' => $this->format($invoiced, $currency),
                ],
                [
                    'heading' => 'Income by month',
                    'rows' => $months,
                    'total' => $invoiced,
                    'total_display' => $this->format($invoiced, $currency),
                ],
            ],
        ];
    }

    public function expenses(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $company->currency;
        $expenses = $this->expenseQuery($company, $from, $to)->load(['category', 'vendor']);
        $total = (int) $expenses->sum('amount');

        $byCategory = $expenses->groupBy(fn (Expense $expense) => $expense->category?->name ?? 'Uncategorised')
            ->map(fn (Collection $group) => (int) $group->sum('amount'));

        $byVendor = $expenses->groupBy(fn (Expense $expense) => $expense->vendor?->name ?? 'Unassigned')
            ->map(fn (Collection $group) => (int) $group->sum('amount'));

        return [
            'key' => 'expenses',
            'title' => 'Expenses',
            'period' => $this->period($from, $to),
            'summary' => [$this->moneyStat('Total expenses', $total, $currency, true)],
            'sections' => [
                [
                    'heading' => 'Expenses by category',
                    'rows' => $byCategory->map(fn (int $amount, string $label) => $this->moneyRow($label, $amount, $currency))->values()->all(),
                    'total' => $total,
                    'total_display' => $this->format($total, $currency),
                ],
                [
                    'heading' => 'Expenses by vendor',
                    'rows' => $byVendor->map(fn (int $amount, string $label) => $this->moneyRow($label, $amount, $currency))->values()->all(),
                    'total' => $total,
                    'total_display' => $this->format($total, $currency),
                ],
            ],
        ];
    }

    public function receivables(Company $company, CarbonInterface $asOf): array
    {
        $currency = $company->currency;
        $today = Carbon::parse($asOf)->startOfDay();

        $invoices = Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->with('customer:id,name')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance() > 0 && $invoice->issue_date->lessThanOrEqualTo($asOf));

        $buckets = ['current' => 0, '1_30' => 0, '31_60' => 0, '61_90' => 0, 'over_90' => 0];
        $rows = [];
        $overdue = 0;
        $outstanding = 0;

        foreach ($invoices as $invoice) {
            $balance = $invoice->balance();
            $outstanding += $balance;
            $days = $invoice->due_date->greaterThan($today) ? 0 : $invoice->due_date->diffInDays($today);

            $bucket = match (true) {
                $days <= 0 => 'current',
                $days <= 30 => '1_30',
                $days <= 60 => '31_60',
                $days <= 90 => '61_90',
                default => 'over_90',
            };

            $buckets[$bucket] += $balance;
            if ($days > 0) {
                $overdue += $balance;
            }

            $rows[] = [
                'label' => $invoice->number.' — '.($invoice->customer?->name ?? 'Unknown'),
                'value' => $balance,
                'display' => $this->format($balance, $currency),
                'meta' => $days > 0 ? "{$days} days overdue" : 'Current',
            ];
        }

        $opening = $this->customerOpeningAdjustments($company, $asOf);
        $outstanding += $opening;

        return [
            'key' => 'receivables',
            'title' => 'Accounts Receivable',
            'period' => $this->period($asOf, $asOf),
            'summary' => [
                $this->moneyStat('Outstanding', $outstanding, $currency, true),
                $this->moneyStat('Overdue', $overdue, $currency),
                $this->moneyStat('Opening balances', $opening, $currency),
            ],
            'sections' => [
                [
                    'heading' => 'Aging',
                    'rows' => [
                        $this->moneyRow('Current', $buckets['current'], $currency),
                        $this->moneyRow('1–30 days', $buckets['1_30'], $currency),
                        $this->moneyRow('31–60 days', $buckets['31_60'], $currency),
                        $this->moneyRow('61–90 days', $buckets['61_90'], $currency),
                        $this->moneyRow('90+ days', $buckets['over_90'], $currency),
                    ],
                    'total' => array_sum($buckets),
                    'total_display' => $this->format(array_sum($buckets), $currency),
                ],
                [
                    'heading' => 'Open invoices',
                    'rows' => $rows,
                    'total' => $outstanding - $opening,
                    'total_display' => $this->format($outstanding - $opening, $currency),
                ],
            ],
        ];
    }

    public function taxSummary(Company $company, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $company->currency;

        $invoices = $this->invoices($company, $from, $to);
        $collected = (int) $invoices->sum('tax_total');
        $taxableSales = (int) $invoices->sum(fn (Invoice $invoice) => $invoice->subtotal - $invoice->discount_total);
        $credits = (int) $this->creditNotes($company, $from, $to)->sum('amount');
        $paid = (int) $this->expenseQuery($company, $from, $to)->sum('tax_amount');

        return [
            'key' => 'tax-summary',
            'title' => 'Tax Summary',
            'period' => $this->period($from, $to),
            'summary' => [
                $this->moneyStat('Tax collected', $collected, $currency),
                $this->moneyStat('Tax paid', $paid, $currency),
                $this->moneyStat('Net tax', $collected - $paid, $currency, true),
            ],
            'sections' => [
                [
                    'heading' => 'Sales',
                    'rows' => [
                        $this->moneyRow('Taxable sales', $taxableSales, $currency),
                        $this->moneyRow('Credit notes', -$credits, $currency),
                        $this->moneyRow('Tax collected', $collected, $currency),
                    ],
                    'total' => $collected,
                    'total_display' => $this->format($collected, $currency),
                ],
                [
                    'heading' => 'Purchases',
                    'rows' => [$this->moneyRow('Tax paid on expenses', $paid, $currency)],
                    'total' => $paid,
                    'total_display' => $this->format($paid, $currency),
                ],
            ],
        ];
    }

    public function customerStatement(Customer $customer, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $customer->effectiveCurrency();
        $opening = $this->customerOpeningBalance($customer, $from);

        $entries = [];

        foreach ($this->invoicesFor($customer, $from, $to) as $invoice) {
            $entries[] = ['date' => $invoice->issue_date, 'type' => 'Invoice', 'reference' => $invoice->number, 'debit' => (int) $invoice->total, 'credit' => 0];
        }

        foreach ($this->paymentsFor($customer, $from, $to) as $payment) {
            $entries[] = ['date' => $payment->date, 'type' => 'Payment', 'reference' => $payment->reference ?? '—', 'debit' => 0, 'credit' => (int) $payment->amount];
        }

        foreach ($this->creditNotesFor($customer, $from, $to) as $credit) {
            $entries[] = ['date' => $credit->issue_date, 'type' => 'Credit note', 'reference' => $credit->number, 'debit' => 0, 'credit' => (int) $credit->amount];
        }

        foreach ($this->openingBalanceEntries($customer, $from, $to) as $transaction) {
            $debit = $transaction->signedAmount() >= 0 ? (int) $transaction->amount : 0;
            $credit = $transaction->signedAmount() < 0 ? (int) $transaction->amount : 0;
            $entries[] = ['date' => $transaction->occurred_on, 'type' => 'Opening balance', 'reference' => '—', 'debit' => $debit, 'credit' => $credit];
        }

        usort($entries, fn ($a, $b) => $a['date'] <=> $b['date']);

        $balance = $opening;
        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($entries as $entry) {
            $balance += $entry['debit'] - $entry['credit'];
            $totalDebit += $entry['debit'];
            $totalCredit += $entry['credit'];

            $rows[] = [
                'date' => $entry['date']->toDateString(),
                'type' => $entry['type'],
                'reference' => $entry['reference'],
                'debit_display' => $entry['debit'] > 0 ? $this->format($entry['debit'], $currency) : '',
                'credit_display' => $entry['credit'] > 0 ? $this->format($entry['credit'], $currency) : '',
                'balance_display' => $this->format($balance, $currency),
            ];
        }

        return [
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email, 'company_name' => $customer->company_name],
            'period' => $this->period($from, $to),
            'opening_display' => $this->format($opening, $currency),
            'total_debit_display' => $this->format($totalDebit, $currency),
            'total_credit_display' => $this->format($totalCredit, $currency),
            'closing_display' => $this->format($balance, $currency),
            'rows' => $rows,
        ];
    }

    // ── Building blocks ───────────────────────────────────────────────────────

    /**
     * General ledger for a bank/cash account or a chart-of-accounts ledger
     * account: opening balance, dated movements with a running balance, and the
     * closing balance.
     *
     * @return array<string, mixed>
     */
    public function generalLedger(Company $company, string $accountType, int $accountId, CarbonInterface $from, CarbonInterface $to): array
    {
        $currency = $company->currency;

        if ($accountType === 'bank') {
            $account = \App\Models\BankAccount::withoutCompanyScope()
                ->where('company_id', $company->id)->find($accountId);
            $opening = $account ? (int) $account->opening_balance : 0;
            $movements = $account
                ? Transaction::withoutCompanyScope()
                    ->where('company_id', $company->id)
                    ->where('bank_account_id', $account->id)
                    ->orderBy('occurred_on')->orderBy('id')
                    ->get()
                : collect();
            $accountName = $account?->name ?? 'Unknown account';
        } else {
            $account = \App\Models\LedgerAccount::withoutCompanyScope()
                ->where('company_id', $company->id)->find($accountId);
            $opening = 0;
            $movements = $account
                ? Transaction::withoutCompanyScope()
                    ->where('company_id', $company->id)
                    ->where('ledger_account_id', $account->id)
                    ->orderBy('occurred_on')->orderBy('id')
                    ->get()
                : collect();
            $accountName = $account?->name ?? 'Unknown account';
        }

        $start = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->endOfDay();

        foreach ($movements as $movement) {
            if ($movement->occurred_on->lessThan($start)) {
                $opening += $movement->signedAmount();
            }
        }

        $balance = $opening;
        $rows = [];
        $totalDebit = 0;
        $totalCredit = 0;

        foreach ($movements as $movement) {
            $date = $movement->occurred_on;
            if ($date->lessThan($start) || $date->greaterThan($end)) {
                continue;
            }

            $signed = $movement->signedAmount();
            $balance += $signed;

            $debit = $signed >= 0 ? abs($signed) : 0;
            $credit = $signed < 0 ? abs($signed) : 0;
            $totalDebit += $debit;
            $totalCredit += $credit;

            $rows[] = [
                'date' => $date->toDateString(),
                'description' => $movement->description,
                'type' => ucfirst($movement->type),
                'debit_display' => $debit > 0 ? $this->format($debit, $currency) : '',
                'credit_display' => $credit > 0 ? $this->format($credit, $currency) : '',
                'balance_display' => $this->format($balance, $currency),
            ];
        }

        return [
            'title' => 'General Ledger — '.$accountName,
            'period' => $this->period($from, $to),
            'account_type' => $accountType,
            'account_id' => $accountId,
            'account_name' => $accountName,
            'currency' => $currency,
            'opening_display' => $this->format($opening, $currency),
            'total_debit_display' => $this->format($totalDebit, $currency),
            'total_credit_display' => $this->format($totalCredit, $currency),
            'closing_display' => $this->format($balance, $currency),
            'rows' => $rows,
        ];
    }

    private function invoices(Company $company, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->get();
    }

    private function creditNotes(Company $company, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CreditNote::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereBetween('issue_date', [$from, $to])
            ->get();
    }

    private function expenseQuery(Company $company, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Expense::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereNull('voided_at')
            ->whereBetween('date', [$from, $to])
            ->get();
    }

    private function directIncome(Company $company, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) Transaction::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('type', 'income')
            ->where('direction', 'in')
            ->whereBetween('occurred_on', [$from, $to])
            ->sum('amount');
    }

    private function invoicesFor(Customer $customer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Invoice::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->get();
    }

    private function paymentsFor(Customer $customer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Payment::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->whereNull('voided_at')
            ->whereBetween('date', [$from, $to])
            ->get();
    }

    private function creditNotesFor(Customer $customer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return CreditNote::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->whereBetween('issue_date', [$from, $to])
            ->get();
    }

    private function customerOpeningBalance(Customer $customer, CarbonInterface $from): int
    {
        $invoices = (int) Invoice::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('issue_date', '<', $from)
            ->sum('total');

        $payments = (int) Payment::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->whereNull('voided_at')
            ->where('date', '<', $from)
            ->sum('amount');

        $credits = (int) CreditNote::withoutCompanyScope()
            ->where('customer_id', $customer->id)
            ->where('issue_date', '<', $from)
            ->sum('amount');

        $opening = $this->customerOpeningAdjustments($customer->company, $from, $customer->id);

        return $invoices - $payments - $credits + $opening;
    }

    private function openingBalanceEntries(Customer $customer, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return Transaction::withoutCompanyScope()
            ->where('company_id', $customer->company_id)
            ->where('source_type', Customer::class)
            ->where('source_id', $customer->id)
            ->whereBetween('occurred_on', [$from, $to])
            ->get();
    }

    private function customerOpeningAdjustments(Company $company, CarbonInterface $asOf, ?int $customerId = null): int
    {
        return (int) Transaction::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('source_type', Customer::class)
            ->when($customerId, fn ($query) => $query->where('source_id', $customerId))
            ->where('occurred_on', '<=', $asOf)
            ->get()
            ->sum(fn (Transaction $transaction) => $transaction->signedAmount());
    }

    /**
     * @param  list<array<string, mixed>>  $revenueRows
     * @param  Collection<string, int>  $expensesByCategory
     */
    private function report(
        string $key,
        string $title,
        CarbonInterface $from,
        CarbonInterface $to,
        array $revenueRows,
        int $revenueTotal,
        string $currency,
        Collection $expensesByCategory,
        int $expensesTotal,
        string $currencyAlias,
    ): array {
        return [
            'key' => $key,
            'title' => $title,
            'period' => $this->period($from, $to),
            'summary' => [
                $this->moneyStat('Revenue', $revenueTotal, $currency),
                $this->moneyStat('Expenses', $expensesTotal, $currencyAlias),
                $this->moneyStat('Net profit', $revenueTotal - $expensesTotal, $currency, true),
            ],
            'sections' => [
                [
                    'heading' => 'Revenue',
                    'rows' => $revenueRows,
                    'total' => $revenueTotal,
                    'total_display' => $this->format($revenueTotal, $currency),
                ],
                [
                    'heading' => 'Expenses',
                    'rows' => $expensesByCategory->map(fn (int $amount, string $label) => $this->moneyRow($label, $amount, $currencyAlias))->values()->all(),
                    'total' => $expensesTotal,
                    'total_display' => $this->format($expensesTotal, $currencyAlias),
                ],
            ],
        ];
    }

    /**
     * @return array{label: string, value: int, display: string, meta?: string}
     */
    private function moneyRow(string $label, int $value, string $currency, ?string $meta = null): array
    {
        return ['label' => $label, 'value' => $value, 'display' => $this->format($value, $currency), 'meta' => $meta];
    }

    /**
     * @return array{label: string, value: int, display: string, emphasis: bool}
     */
    private function moneyStat(string $label, int $value, string $currency, bool $emphasis = false): array
    {
        return ['label' => $label, 'value' => $value, 'display' => $this->format($value, $currency), 'emphasis' => $emphasis];
    }

    private function format(int $amount, string $currency): string
    {
        return Money::of($amount, $currency)->format();
    }

    /**
     * @return array{from: string, to: string}
     */
    private function period(CarbonInterface $from, CarbonInterface $to): array
    {
        return ['from' => Carbon::parse($from)->toDateString(), 'to' => Carbon::parse($to)->toDateString()];
    }
}

<?php

namespace App\Services\Reporting;

use App\Enums\InvoiceStatus;
use App\Enums\TransactionType;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Dashboard aggregation. Reuses ReportService for period KPIs and adds
 * time-series and widget data.
 */
class DashboardService
{
    /**
     * @var list<string>
     */
    private const OPEN_STATUSES = [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value];

    public function __construct(private readonly ReportService $reports) {}

    public function overview(Company $company, CarbonInterface $from, CarbonInterface $to, string $trend = 'monthly'): array
    {
        $currency = $company->currency;
        $pl = $this->reports->profitAndLoss($company, $from, $to);
        $receivables = $this->reports->receivables($company, $to);
        $expenses = $this->reports->expenses($company, $from, $to);

        return [
            'period' => ['from' => Carbon::parse($from)->toDateString(), 'to' => Carbon::parse($to)->toDateString()],
            'kpis' => [
                ['label' => 'Total revenue', 'value' => $pl['summary'][0]['display']],
                ['label' => 'Total expenses', 'value' => $pl['summary'][1]['display']],
                ['label' => 'Outstanding invoices', 'value' => $receivables['summary'][0]['display']],
                ['label' => 'Overdue invoices', 'value' => $receivables['summary'][1]['display']],
                ['label' => 'Net income', 'value' => $pl['summary'][2]['display'], 'emphasis' => true],
            ],
            'revenueVsExpenses' => $this->monthlySeries($company, 6),
            'revenueTrend' => $trend === 'weekly'
                ? $this->weeklyRevenue($company, 12)
                : $this->monthlyRevenue($company, 12),
            'expenseBreakdown' => $expenses['sections'][0]['rows'],
            'recentTransactions' => $this->recentTransactions($company),
            'outstandingInvoices' => $this->outstandingInvoices($company),
            'currency' => $currency,
        ];
    }

    /**
     * @return list<array{label: string, revenue: int, expenses: int}>
     */
    private function monthlySeries(Company $company, int $months): array
    {
        return collect(range($months - 1, 0))->map(function (int $offset) use ($company) {
            $month = now()->startOfMonth()->subMonths($offset);
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth();

            return [
                'label' => $month->format('M'),
                'revenue' => $this->revenueFor($company, $from, $to),
                'expenses' => (int) $this->expenseQuery($company, $from, $to)->sum('amount'),
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function monthlyRevenue(Company $company, int $months): array
    {
        return collect(range($months - 1, 0))->map(function (int $offset) use ($company) {
            $month = now()->startOfMonth()->subMonths($offset);

            return [
                'label' => $month->format('M Y'),
                'value' => $this->revenueFor($company, $month->copy()->startOfMonth(), $month->copy()->endOfMonth()),
            ];
        })->all();
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function weeklyRevenue(Company $company, int $weeks): array
    {
        return collect(range($weeks - 1, 0))->map(function (int $offset) use ($company) {
            $weekStart = now()->startOfWeek()->subWeeks($offset);

            return [
                'label' => $weekStart->format('d M'),
                'value' => $this->revenueFor($company, $weekStart->copy()->startOfDay(), $weekStart->copy()->endOfWeek()),
            ];
        })->all();
    }

    private function revenueFor(Company $company, CarbonInterface $from, CarbonInterface $to): int
    {
        $invoiced = (int) Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereBetween('issue_date', [$from, $to])
            ->sum('total');

        $credits = (int) CreditNote::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereBetween('issue_date', [$from, $to])
            ->sum('amount');

        $direct = (int) Transaction::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('type', TransactionType::Income->value)
            ->where('direction', 'in')
            ->whereBetween('occurred_on', [$from, $to])
            ->sum('amount');

        return $invoiced - $credits + $direct;
    }

    private function expenseQuery(Company $company, CarbonInterface $from, CarbonInterface $to)
    {
        return Expense::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereNull('voided_at')
            ->whereBetween('date', [$from, $to]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentTransactions(Company $company): array
    {
        return Transaction::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'occurred_on' => $transaction->occurred_on->toDateString(),
                'description' => $transaction->description,
                'type_label' => TransactionType::tryFrom($transaction->type)?->label() ?? $transaction->type,
                'direction' => $transaction->direction,
                'amount_display' => Money::of((int) $transaction->amount, $transaction->currency)->format(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function outstandingInvoices(Company $company): array
    {
        return Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->with('customer:id,name')
            ->orderBy('due_date')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance() > 0)
            ->take(8)
            ->map(function (Invoice $invoice) {
                $status = $invoice->displayStatus();

                return [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'customer_name' => $invoice->customer?->name,
                    'due_date' => $invoice->due_date->toDateString(),
                    'balance_display' => Money::of($invoice->balance(), $invoice->currency)->format(),
                    'status_label' => $status->label(),
                    'status_class' => $status->badgeClasses(),
                ];
            })
            ->values()
            ->all();
    }
}

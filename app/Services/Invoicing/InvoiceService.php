<?php

namespace App\Services\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\DocumentNumberService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates and mutates invoices, always deriving totals through the calculator.
 */
class InvoiceService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly DocumentNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data): Invoice
    {
        return DB::transaction(function () use ($company, $data) {
            $currency = $company->currency;
            $recurring = (bool) ($data['is_recurring'] ?? false);
            $lines = $this->normalizeLines($data['items'], $currency);
            $totals = $this->calculator->calculate(
                $lines,
                $this->discount($data),
                (bool) $company->tax_inclusive,
                $currency,
            );

            $invoice = Invoice::create([
                'customer_id' => $data['customer_id'],
                'number' => $this->numbers->next($company, 'invoice'),
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'currency' => $currency,
                'tax_inclusive' => (bool) $company->tax_inclusive,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'is_recurring' => $recurring,
                'recurrence_interval' => $recurring ? ($data['recurrence_interval'] ?? 'monthly') : null,
                'next_recurrence_on' => $recurring
                    ? $this->advance($data['issue_date'], $data['recurrence_interval'] ?? 'monthly')->toDateString()
                    : null,
            ]);

            $this->persistItems($invoice, $lines, $totals['lines']);

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            $currency = $invoice->currency;
            $recurring = (bool) ($data['is_recurring'] ?? false);
            $lines = $this->normalizeLines($data['items'], $currency);
            $totals = $this->calculator->calculate(
                $lines,
                $this->discount($data),
                (bool) $invoice->tax_inclusive,
                $currency,
            );

            $invoice->update([
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'is_recurring' => $recurring,
                'recurrence_interval' => $recurring ? ($data['recurrence_interval'] ?? 'monthly') : null,
                'next_recurrence_on' => $recurring
                    ? ($invoice->is_recurring && $invoice->next_recurrence_on
                        ? $invoice->next_recurrence_on->toDateString()
                        : $this->advance($data['issue_date'], $data['recurrence_interval'] ?? 'monthly')->toDateString())
                    : null,
            ]);

            $invoice->items()->delete();
            $this->persistItems($invoice, $lines, $totals['lines']);

            return $invoice;
        });
    }

    public function duplicate(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            $company = $invoice->company;

            $copy = Invoice::create([
                'customer_id' => $invoice->customer_id,
                'number' => $this->numbers->next($company, 'invoice'),
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($company->default_payment_terms_days)->toDateString(),
                'currency' => $invoice->currency,
                'tax_inclusive' => $invoice->tax_inclusive,
                'discount_type' => $invoice->discount_type,
                'discount_value' => $invoice->discount_value,
                'subtotal' => $invoice->subtotal,
                'discount_total' => $invoice->discount_total,
                'tax_total' => $invoice->tax_total,
                'total' => $invoice->total,
                'notes' => $invoice->notes,
                'terms' => $invoice->terms,
            ]);

            foreach ($invoice->items as $item) {
                $copy->items()->create($item->only([
                    'product_id', 'description', 'quantity', 'unit_price',
                    'discount_type', 'discount_value', 'tax_rate',
                    'line_subtotal', 'line_discount', 'line_tax', 'line_total', 'position',
                ]));
            }

            return $copy;
        });
    }

    /**
     * Generate the next draft occurrence of a recurring invoice template.
     */
    public function generateRecurring(Invoice $template): ?Invoice
    {
        if (! $template->isRecurringTemplate() || ! $template->next_recurrence_on) {
            return null;
        }

        if ($template->next_recurrence_on->isAfter(now())) {
            return null;
        }

        return DB::transaction(function () use ($template) {
            $company = $template->company;
            $issue = $template->next_recurrence_on;

            $child = Invoice::create([
                'company_id' => $company->id,
                'customer_id' => $template->customer_id,
                'number' => $this->numbers->next($company, 'invoice'),
                'status' => InvoiceStatus::Draft->value,
                'issue_date' => $issue->toDateString(),
                'due_date' => $issue->copy()->addDays($company->default_payment_terms_days)->toDateString(),
                'currency' => $template->currency,
                'tax_inclusive' => $template->tax_inclusive,
                'discount_type' => $template->discount_type,
                'discount_value' => $template->discount_value,
                'subtotal' => $template->subtotal,
                'discount_total' => $template->discount_total,
                'tax_total' => $template->tax_total,
                'total' => $template->total,
                'notes' => $template->notes,
                'terms' => $template->terms,
                'recurrence_parent_id' => $template->id,
            ]);

            foreach ($template->items as $item) {
                $child->items()->create($item->only([
                    'product_id', 'description', 'quantity', 'unit_price',
                    'discount_type', 'discount_value', 'tax_rate',
                    'line_subtotal', 'line_discount', 'line_tax', 'line_total', 'position',
                ]));
            }

            $template->update([
                'next_recurrence_on' => $this->advance($issue, $template->recurrence_interval ?? 'monthly'),
                'last_generated_at' => now(),
            ]);

            return $child->fresh(['items']);
        });
    }

    private function advance(CarbonInterface|string $date, string $interval): CarbonInterface
    {
        $date = $date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date);

        return match ($interval) {
            'weekly' => $date->addWeek(),
            'yearly' => $date->addYear(),
            default => $date->addMonth(),
        };
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function normalizeLines(array $items, string $currency): array
    {
        return array_map(fn (array $item, int $index) => [
            'product_id' => $item['product_id'] ?? null,
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit_price' => Money::fromDecimal((string) $item['unit_price'], $currency)->amount(),
            'discount_type' => $item['discount_type'] ?? null,
            'discount_value' => $item['discount_value'] ?? null,
            'tax_rate' => $item['tax_rate'] ?? null,
            'position' => $index,
        ], $items, array_keys($items));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{type: ?string, value: int|float|string|null}|null
     */
    private function discount(array $data): ?array
    {
        if (empty($data['discount_type']) || ! isset($data['discount_value'])) {
            return null;
        }

        return ['type' => $data['discount_type'], 'value' => $data['discount_value']];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, int>>  $totals
     */
    private function persistItems(Invoice $invoice, array $lines, array $totals): void
    {
        foreach ($lines as $index => $line) {
            $invoice->items()->create([
                'product_id' => $line['product_id'],
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'discount_type' => $line['discount_type'],
                'discount_value' => $line['discount_value'],
                'tax_rate' => $line['tax_rate'],
                'line_subtotal' => $totals[$index]['subtotal'],
                'line_discount' => $totals[$index]['discount'],
                'line_tax' => $totals[$index]['tax'],
                'line_total' => $totals[$index]['total'],
                'position' => $index,
            ]);
        }
    }
}

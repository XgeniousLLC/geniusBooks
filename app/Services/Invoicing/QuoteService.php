<?php

namespace App\Services\Invoicing;

use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\DocumentNumberService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Creates and mutates quotes (same calculation engine as invoices) and turns
 * accepted quotes into invoices.
 */
class QuoteService
{
    public function __construct(
        private readonly InvoiceCalculator $calculator,
        private readonly DocumentNumberService $numbers,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Company $company, array $data): Quote
    {
        return DB::transaction(function () use ($company, $data) {
            $currency = $company->currency;
            $lines = $this->normalizeLines($data['items'], $currency);
            $totals = $this->calculator->calculate($lines, $this->discount($data), (bool) $company->tax_inclusive, $currency);

            $quote = Quote::create([
                'customer_id' => $data['customer_id'],
                'number' => $this->numbers->next($company, 'quote'),
                'status' => QuoteStatus::Draft->value,
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
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
            ]);

            $this->persistItems($quote, $lines, $totals['lines']);

            return $quote;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Quote $quote, array $data): Quote
    {
        return DB::transaction(function () use ($quote, $data) {
            $currency = $quote->currency;
            $lines = $this->normalizeLines($data['items'], $currency);
            $totals = $this->calculator->calculate($lines, $this->discount($data), (bool) $quote->tax_inclusive, $currency);

            $quote->update([
                'customer_id' => $data['customer_id'],
                'issue_date' => $data['issue_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'discount_type' => $data['discount_type'] ?? null,
                'discount_value' => $data['discount_value'] ?? null,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'tax_total' => $totals['tax'],
                'total' => $totals['total'],
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
            ]);

            $quote->items()->delete();
            $this->persistItems($quote, $lines, $totals['lines']);

            return $quote;
        });
    }

    /**
     * Convert a quote into a draft invoice and link them.
     */
    public function convert(Quote $quote): Invoice
    {
        return DB::transaction(function () use ($quote) {
            $company = $quote->company;
            $currency = $quote->currency;

            $invoice = $this->invoices->create($company, [
                'customer_id' => $quote->customer_id,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays($company->default_payment_terms_days)->toDateString(),
                'discount_type' => $quote->discount_type,
                'discount_value' => $quote->discount_value,
                'notes' => $quote->notes,
                'terms' => $quote->terms,
                'items' => $quote->items->map(fn ($item) => [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => Money::of((int) $item->unit_price, $currency)->toDecimal(),
                    'discount_type' => $item->discount_type,
                    'discount_value' => $item->discount_value,
                    'tax_rate' => $item->tax_rate !== null ? (float) $item->tax_rate : null,
                ])->values()->all(),
            ]);

            $quote->update([
                'status' => QuoteStatus::Converted->value,
                'converted_invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
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
    private function persistItems(Quote $quote, array $lines, array $totals): void
    {
        foreach ($lines as $index => $line) {
            $quote->items()->create([
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

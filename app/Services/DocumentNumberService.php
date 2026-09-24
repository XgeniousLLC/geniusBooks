<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DocumentSequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Allocates sequential, per-company document numbers.
 *
 * Numbers are consumed on issue and never reused. A voided document keeps its
 * number (sequence gaps are permitted by design). Allocation is guarded by a
 * row lock and a unique (company_id, type) constraint.
 */
class DocumentNumberService
{
    public function next(
        Company $company,
        string $type,
        ?string $prefix = null,
        ?int $padding = null,
    ): string {
        return DB::transaction(function () use ($company, $type, $prefix, $padding) {
            $sequence = DocumentSequence::withoutCompanyScope()->firstOrCreate(
                ['company_id' => $company->id, 'type' => $type],
                [
                    'prefix' => $prefix ?? $this->defaultPrefix($company, $type),
                    'padding' => $padding ?? $this->defaultPadding($company, $type),
                    'next_number' => 1,
                ],
            );

            $sequence = DocumentSequence::withoutCompanyScope()
                ->whereKey($sequence->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $number = (int) $sequence->next_number;
            $sequence->next_number = $number + 1;
            $sequence->save();

            return $this->format($sequence->prefix, $number, (int) $sequence->padding);
        }, 3);
    }

    /**
     * The number that would be issued next, without consuming it.
     */
    public function preview(
        Company $company,
        string $type,
        ?string $prefix = null,
        ?int $padding = null,
    ): string {
        $sequence = DocumentSequence::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->where('type', $type)
            ->first();

        $prefix ??= $sequence->prefix ?? $this->defaultPrefix($company, $type);
        $padding ??= (int) ($sequence->padding ?? $this->defaultPadding($company, $type));
        $number = (int) ($sequence->next_number ?? 1);

        return $this->format($prefix, $number, $padding);
    }

    public function format(?string $prefix, int $number, int $padding): string
    {
        return ($prefix ?? '').str_pad((string) $number, max($padding, 1), '0', STR_PAD_LEFT);
    }

    private function defaultPrefix(Company $company, string $type): string
    {
        return match ($type) {
            'invoice' => $company->invoice_prefix ?: 'INV-',
            'credit_note' => 'CN-',
            'quote' => 'QT-',
            default => strtoupper(Str::substr($type, 0, 3)).'-',
        };
    }

    private function defaultPadding(Company $company, string $type): int
    {
        return match ($type) {
            'invoice' => (int) ($company->invoice_number_padding ?: 4),
            default => 4,
        };
    }
}

<?php

namespace App\Services\Imports;

/**
 * Outcome of a CSV import attempt.
 *
 * Imports are all-or-nothing on validation: if any row fails validation,
 * nothing is written and `errors` is populated. Duplicate rows are skipped and
 * reported separately.
 */
final class ImportResult
{
    /**
     * @param  list<array{row: int, message: string}>  $errors
     */
    public function __construct(
        public readonly int $imported = 0,
        public readonly int $skipped = 0,
        public readonly array $errors = [],
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isSuccess(): bool
    {
        return ! $this->hasErrors();
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'imported' => $this->imported,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
        ];
    }
}

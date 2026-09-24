<?php

namespace App\Services\Imports;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports customers and products from CSV.
 *
 * Validation is all-or-nothing: any invalid row aborts the whole import.
 * Duplicate rows (existing records) are skipped and reported.
 */
class CsvImportService
{
    /** @var list<string> */
    public const CUSTOMER_COLUMNS = [
        'name', 'company_name', 'email', 'phone',
        'billing_address', 'shipping_address', 'tax_id',
        'payment_terms_days', 'notes',
    ];

    /** @var list<string> */
    public const PRODUCT_COLUMNS = [
        'name', 'sku', 'description', 'type', 'unit_price', 'tax_rate', 'category',
    ];

    public function importCustomers(Company $company, string $path): ImportResult
    {
        $rows = $this->read($path);

        $existingEmails = Customer::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereNotNull('email')
            ->pluck('email')
            ->map(fn ($email) => strtolower((string) $email))
            ->all();

        $errors = [];
        $valid = [];
        $skipped = 0;
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['__line'];
            $name = trim((string) ($row['name'] ?? ''));
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $terms = $this->nullableInt($row['payment_terms_days'] ?? null);

            if ($name === '') {
                $errors[] = ['row' => $line, 'message' => 'name is required'];

                continue;
            }

            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = ['row' => $line, 'message' => 'email is invalid'];

                continue;
            }

            if ($terms !== null && ($terms < 0 || $terms > 365)) {
                $errors[] = ['row' => $line, 'message' => 'payment_terms_days must be between 0 and 365'];

                continue;
            }

            $dedupeKey = $email !== '' ? $email : 'name:'.strtolower($name);

            if (($email !== '' && in_array($email, $existingEmails, true)) || isset($seen[$dedupeKey])) {
                $skipped++;

                continue;
            }

            $seen[$dedupeKey] = true;

            $valid[] = [
                'name' => $name,
                'company_name' => $this->nullableString($row['company_name'] ?? null),
                'email' => $email !== '' ? $email : null,
                'phone' => $this->nullableString($row['phone'] ?? null),
                'billing_address' => $this->nullableString($row['billing_address'] ?? null),
                'shipping_address' => $this->nullableString($row['shipping_address'] ?? null),
                'tax_id' => $this->nullableString($row['tax_id'] ?? null),
                'currency' => $company->currency,
                'payment_terms_days' => $terms ?? $company->default_payment_terms_days,
                'notes' => $this->nullableString($row['notes'] ?? null),
                'is_active' => true,
            ];
        }

        if ($errors !== []) {
            return new ImportResult(0, $skipped, $errors);
        }

        DB::transaction(function () use ($valid) {
            foreach ($valid as $attributes) {
                Customer::create($attributes);
            }
        });

        return new ImportResult(count($valid), $skipped);
    }

    public function importProducts(Company $company, string $path): ImportResult
    {
        $rows = $this->read($path);

        $existingSkus = Product::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->whereNotNull('sku')
            ->pluck('sku')
            ->map(fn ($sku) => strtolower((string) $sku))
            ->all();

        $errors = [];
        $valid = [];
        $skipped = 0;
        $seen = [];

        foreach ($rows as $row) {
            $line = $row['__line'];
            $name = trim((string) ($row['name'] ?? ''));
            $sku = strtolower(trim((string) ($row['sku'] ?? '')));
            $type = strtolower(trim((string) ($row['type'] ?? 'service')));
            $price = trim((string) ($row['unit_price'] ?? '0'));
            $taxRate = $this->nullableNumeric($row['tax_rate'] ?? null);

            if ($name === '') {
                $errors[] = ['row' => $line, 'message' => 'name is required'];

                continue;
            }

            if (! in_array($type, Product::TYPES, true)) {
                $errors[] = ['row' => $line, 'message' => 'type must be product or service'];

                continue;
            }

            if (! is_numeric($price) || (float) $price < 0) {
                $errors[] = ['row' => $line, 'message' => 'unit_price must be a positive number'];

                continue;
            }

            if ($taxRate !== null && ($taxRate < 0 || $taxRate > 100)) {
                $errors[] = ['row' => $line, 'message' => 'tax_rate must be between 0 and 100'];

                continue;
            }

            $dedupeKey = $sku !== '' ? $sku : 'name:'.strtolower($name);

            if (($sku !== '' && in_array($sku, $existingSkus, true)) || isset($seen[$dedupeKey])) {
                $skipped++;

                continue;
            }

            $seen[$dedupeKey] = true;

            $valid[] = [
                'name' => $name,
                'sku' => $sku !== '' ? $sku : null,
                'description' => $this->nullableString($row['description'] ?? null),
                'type' => $type,
                'unit_price' => Money::fromDecimal((string) $price, $company->currency)->amount(),
                'tax_rate' => $taxRate,
                'category' => $this->nullableString($row['category'] ?? null),
                'is_active' => true,
            ];
        }

        if ($errors !== []) {
            return new ImportResult(0, $skipped, $errors);
        }

        DB::transaction(function () use ($valid) {
            foreach ($valid as $attributes) {
                Product::create($attributes);
            }
        });

        return new ImportResult(count($valid), $skipped);
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function read(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new InvalidArgumentException('Unable to read the uploaded file.');
        }

        try {
            $header = fgetcsv($handle);

            if ($header === false || $header === [null]) {
                throw new InvalidArgumentException('The file is empty.');
            }

            $header = array_map(fn ($column) => strtolower(trim((string) $column)), $header);

            if (! in_array('name', $header, true)) {
                throw new InvalidArgumentException('The file must include a "name" column.');
            }

            $rows = [];
            $line = 1;

            while (($data = fgetcsv($handle)) !== false) {
                $line++;

                if ($this->isEmptyRow($data)) {
                    continue;
                }

                $data = array_slice(array_pad($data, count($header), null), 0, count($header));
                $row = array_combine($header, $data);
                $row['__line'] = $line;

                $rows[] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  array<int, string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function nullableNumeric(mixed $value): ?float
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}

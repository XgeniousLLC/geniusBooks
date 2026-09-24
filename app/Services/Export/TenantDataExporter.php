<?php

namespace App\Services\Export;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use ZipArchive;

/**
 * Builds a portable ZIP archive of a tenant's business data (CSV per module).
 */
class TenantDataExporter
{
    public function export(Company $company): string
    {
        $path = tempnam(sys_get_temp_dir(), 'tenant-export').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('customers.csv', $this->csv(
            ['id', 'name', 'company_name', 'email', 'phone', 'currency', 'payment_terms_days', 'is_active'],
            Customer::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (Customer $c) => [
                $c->id, $c->name, $c->company_name, $c->email, $c->phone, $c->currency, $c->payment_terms_days, $c->is_active ? 1 : 0,
            ]),
        ));

        $zip->addFromString('invoices.csv', $this->csv(
            ['id', 'number', 'customer_id', 'status', 'issue_date', 'due_date', 'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid', 'credit_total'],
            Invoice::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (Invoice $i) => [
                $i->id, $i->number, $i->customer_id, $i->status, $i->issue_date->toDateString(), $i->due_date->toDateString(),
                $i->subtotal, $i->discount_total, $i->tax_total, $i->total, $i->amount_paid, $i->credit_total,
            ]),
        ));

        $zip->addFromString('payments.csv', $this->csv(
            ['id', 'customer_id', 'date', 'amount', 'method', 'reference'],
            Payment::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (Payment $p) => [
                $p->id, $p->customer_id, $p->date->toDateString(), $p->amount, $p->method, $p->reference,
            ]),
        ));

        $zip->addFromString('credit_notes.csv', $this->csv(
            ['id', 'number', 'customer_id', 'invoice_id', 'issue_date', 'amount', 'status'],
            CreditNote::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (CreditNote $c) => [
                $c->id, $c->number, $c->customer_id, $c->invoice_id, $c->issue_date->toDateString(), $c->amount, $c->status,
            ]),
        ));

        $zip->addFromString('expenses.csv', $this->csv(
            ['id', 'date', 'description', 'amount', 'tax_amount', 'vendor_id', 'expense_category_id', 'currency'],
            Expense::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (Expense $e) => [
                $e->id, $e->date->toDateString(), $e->description, $e->amount, $e->tax_amount, $e->vendor_id, $e->expense_category_id, $e->currency,
            ]),
        ));

        $zip->addFromString('transactions.csv', $this->csv(
            ['id', 'occurred_on', 'type', 'direction', 'amount', 'currency', 'description', 'bank_account_id', 'ledger_account_id'],
            Transaction::withoutCompanyScope()->where('company_id', $company->id)->get()->map(fn (Transaction $t) => [
                $t->id, $t->occurred_on->toDateString(), $t->type, $t->direction, $t->amount, $t->currency, $t->description, $t->bank_account_id, $t->ledger_account_id,
            ]),
        ));

        $zip->addFromString('README.txt', "Data export for {$company->name}\nGenerated ".now()->toDateTimeString()."\n");

        $zip->close();

        return $path;
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function csv(array $headers, iterable $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $headers);

        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }
}

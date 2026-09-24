<?php

namespace App\Console\Commands;

use App\Enums\InvoiceStatus;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Services\Invoicing\InvoiceDeliveryService;
use Illuminate\Console\Command;
use Throwable;

class SendInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-reminders';

    protected $description = 'Queue due-soon and overdue invoice reminder emails';

    public function handle(InvoiceDeliveryService $delivery): int
    {
        $today = now()->startOfDay();
        $due = 0;
        $overdue = 0;

        Invoice::withoutCompanyScope()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value])
            ->whereColumn('amount_paid', '<', 'total')
            ->whereNull('due_reminder_sent_at')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->with(['customer', 'company'])
            ->chunkById(100, function ($invoices) use ($delivery, $today, &$due) {
                foreach ($invoices as $invoice) {
                    $company = $invoice->company;

                    if (! $company || ! $company->reminders_enabled || ! $company->notify_invoice_due) {
                        continue;
                    }

                    if ($invoice->due_date->greaterThan($today->copy()->addDays($company->reminder_days_before))) {
                        continue;
                    }

                    if ($this->send($delivery, $invoice, EmailTemplate::INVOICE_DUE)) {
                        $invoice->update(['due_reminder_sent_at' => now()]);
                        $due++;
                    }
                }
            });

        Invoice::withoutCompanyScope()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value])
            ->whereColumn('amount_paid', '<', 'total')
            ->whereNull('overdue_reminder_sent_at')
            ->whereDate('due_date', '<', $today->toDateString())
            ->with(['customer', 'company'])
            ->chunkById(100, function ($invoices) use ($delivery, $today, &$overdue) {
                foreach ($invoices as $invoice) {
                    $company = $invoice->company;

                    if (! $company || ! $company->reminders_enabled || ! $company->notify_invoice_overdue) {
                        continue;
                    }

                    $daysOverdue = $invoice->due_date->diffInDays($today);

                    if ($daysOverdue < $company->reminder_days_overdue) {
                        continue;
                    }

                    if ($this->send($delivery, $invoice, EmailTemplate::INVOICE_OVERDUE)) {
                        $invoice->update(['overdue_reminder_sent_at' => now()]);
                        $overdue++;
                    }
                }
            });

        $this->info("Queued {$due} due reminder(s) and {$overdue} overdue reminder(s).");

        return self::SUCCESS;
    }

    private function send(InvoiceDeliveryService $delivery, Invoice $invoice, string $template): bool
    {
        try {
            $delivery->send($invoice, $template);

            return true;
        } catch (Throwable $e) {
            report($e);
            $this->warn("Invoice {$invoice->number}: {$e->getMessage()}");

            return false;
        }
    }
}

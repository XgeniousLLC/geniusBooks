<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Services\Invoicing\InvoiceService;
use Illuminate\Console\Command;
use Throwable;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate draft invoices for due recurring invoice templates';

    public function handle(InvoiceService $invoices): int
    {
        $generated = 0;

        Invoice::withoutCompanyScope()
            ->recurring()
            ->whereNotNull('next_recurrence_on')
            ->whereDate('next_recurrence_on', '<=', now()->toDateString())
            ->with('company')
            ->chunkById(100, function ($templates) use ($invoices, &$generated) {
                foreach ($templates as $template) {
                    if (! $template->company?->is_active) {
                        continue;
                    }

                    try {
                        if ($invoices->generateRecurring($template)) {
                            $generated++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $this->warn("Invoice #{$template->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Generated {$generated} recurring invoice draft(s).");

        return self::SUCCESS;
    }
}

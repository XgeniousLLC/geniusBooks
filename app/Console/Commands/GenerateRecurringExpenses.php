<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Services\Accounting\ExpenseService;
use Illuminate\Console\Command;
use Throwable;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Generate occurrences for due recurring expenses';

    public function handle(ExpenseService $service): int
    {
        $generated = 0;

        Expense::withoutCompanyScope()
            ->recurring()
            ->whereNotNull('next_recurrence_on')
            ->whereDate('next_recurrence_on', '<=', now()->toDateString())
            ->with('company')
            ->chunkById(100, function ($templates) use ($service, &$generated) {
                foreach ($templates as $template) {
                    if (! $template->company?->is_active) {
                        continue;
                    }

                    try {
                        if ($service->generateRecurring($template)) {
                            $generated++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                        $this->warn("Expense #{$template->id}: {$e->getMessage()}");
                    }
                }
            });

        $this->info("Generated {$generated} recurring expense(s).");

        return self::SUCCESS;
    }
}

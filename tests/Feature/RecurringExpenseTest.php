<?php

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\Transaction;

it('generates the next occurrence for a due recurring expense', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 100000]);

    $template = Expense::factory()->for($company)->create([
        'bank_account_id' => $account->id,
        'amount' => 5000,
        'is_recurring' => true,
        'recurrence_interval' => 'monthly',
        'next_recurrence_on' => now()->subDay()->toDateString(),
    ]);

    $this->artisan('expenses:generate-recurring')->assertExitCode(0);

    $child = Expense::where('recurrence_parent_id', $template->id)->first();

    expect($child)->not->toBeNull()
        ->and($child->amount)->toBe(5000)
        ->and($child->transaction_id)->not->toBeNull()
        ->and($template->fresh()->next_recurrence_on->isFuture())->toBeTrue()
        ->and(Transaction::where('type', 'expense')->where('direction', 'out')->count())->toBe(1);
});

it('does not generate for expenses that are not due', function () {
    [$user, $company] = userWithCompany('owner');

    Expense::factory()->for($company)->create([
        'is_recurring' => true,
        'recurrence_interval' => 'monthly',
        'next_recurrence_on' => now()->addMonth()->toDateString(),
    ]);

    $this->artisan('expenses:generate-recurring')->assertExitCode(0);

    expect(Expense::count())->toBe(1);
});

it('does not generate for voided recurring expenses', function () {
    [$user, $company] = userWithCompany('owner');

    $template = Expense::factory()->for($company)->create([
        'is_recurring' => true,
        'recurrence_interval' => 'monthly',
        'next_recurrence_on' => now()->subDay()->toDateString(),
        'voided_at' => now(),
    ]);

    $this->artisan('expenses:generate-recurring')->assertExitCode(0);

    expect(Expense::where('recurrence_parent_id', $template->id)->count())->toBe(0);
});

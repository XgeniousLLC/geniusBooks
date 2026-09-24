<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'vendor_id' => null,
            'expense_category_id' => null,
            'bank_account_id' => fn (array $attributes) => BankAccount::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'date' => now()->toDateString(),
            'amount' => fake()->numberBetween(1000, 100000),
            'tax_amount' => 0,
            'currency' => 'USD',
            'description' => fake()->sentence(3),
            'reference' => null,
            'notes' => null,
            'is_recurring' => false,
            'recurrence_interval' => null,
            'next_recurrence_on' => null,
            'voided_at' => null,
        ];
    }

    public function recurring(string $interval = 'monthly'): static
    {
        return $this->state(fn () => [
            'is_recurring' => true,
            'recurrence_interval' => $interval,
            'next_recurrence_on' => now()->addMonth()->toDateString(),
        ]);
    }
}

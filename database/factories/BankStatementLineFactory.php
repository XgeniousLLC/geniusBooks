<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankStatementLine>
 */
class BankStatementLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'bank_account_id' => fn (array $attributes) => BankAccount::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'matched_transaction_id' => null,
            'date' => now()->toDateString(),
            'description' => fake()->sentence(3),
            'reference' => null,
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'reconciled_at' => null,
        ];
    }
}

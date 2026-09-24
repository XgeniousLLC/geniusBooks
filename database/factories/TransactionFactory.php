<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'bank_account_id' => null,
            'type' => 'adjustment',
            'direction' => 'in',
            'amount' => fake()->numberBetween(1000, 100000),
            'currency' => 'USD',
            'description' => fake()->sentence(3),
            'occurred_on' => now()->toDateString(),
        ];
    }

    public function forAccount(BankAccount $account, string $direction = 'in'): static
    {
        return $this->state(fn () => [
            'company_id' => $account->company_id,
            'bank_account_id' => $account->id,
            'currency' => $account->currency,
            'direction' => $direction,
        ]);
    }
}

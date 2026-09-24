<?php

namespace Database\Factories;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'bank_account_id' => fn (array $attributes) => BankAccount::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'date' => now()->toDateString(),
            'amount' => fake()->numberBetween(1000, 100000),
            'method' => 'bank_transfer',
            'reference' => strtoupper(fake()->bothify('PAY-####')),
            'notes' => null,
            'voided_at' => null,
        ];
    }
}

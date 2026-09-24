<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CreditNote>
 */
class CreditNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'invoice_id' => null,
            'number' => 'CN-'.fake()->unique()->numerify('######'),
            'issue_date' => now()->toDateString(),
            'amount' => fake()->numberBetween(1000, 50000),
            'reason' => fake()->sentence(3),
            'status' => 'applied',
        ];
    }
}

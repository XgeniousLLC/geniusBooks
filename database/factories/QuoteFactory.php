<?php

namespace Database\Factories;

use App\Enums\QuoteStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Quote>
 */
class QuoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'number' => 'QT-'.fake()->unique()->numerify('######'),
            'status' => QuoteStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
            'currency' => 'USD',
            'tax_inclusive' => false,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'notes' => null,
            'terms' => 'Valid for 30 days.',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Sent->value, 'sent_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => QuoteStatus::Accepted->value, 'sent_at' => now()]);
    }
}

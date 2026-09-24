<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => fn (array $attributes) => Customer::factory()
                ->create(['company_id' => $attributes['company_id']])->id,
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => InvoiceStatus::Draft->value,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'currency' => 'USD',
            'tax_inclusive' => false,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'notes' => null,
            'terms' => 'Payment due within 15 days.',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Sent->value,
            'sent_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Cancelled->value,
            'cancelled_at' => now(),
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn () => [
            'status' => InvoiceStatus::Sent->value,
            'sent_at' => now()->subDays(30),
            'issue_date' => now()->subDays(30)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]);
    }
}

<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'financial_year_start_month' => 1,
            'financial_year_start_day' => 1,
            'tax_inclusive' => false,
            'default_tax_rate' => 0,
            'invoice_prefix' => 'INV-',
            'invoice_number_padding' => 4,
            'default_payment_terms_days' => 15,
            'is_active' => true,
            'onboarded_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

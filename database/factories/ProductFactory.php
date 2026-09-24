<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(3, true),
            'sku' => strtoupper(fake()->unique()->bothify('SKU-####')),
            'description' => fake()->sentence(),
            'type' => fake()->randomElement(['product', 'service']),
            'unit_price' => fake()->numberBetween(1000, 500000),
            'tax_rate' => fake()->randomElement([0, 5, 10, 20]),
            'category' => fake()->randomElement(['Consulting', 'Software', 'Hardware', 'Training']),
            'is_active' => true,
        ];
    }

    public function service(): static
    {
        return $this->state(fn () => ['type' => 'service']);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

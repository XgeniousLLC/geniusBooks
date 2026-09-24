<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LedgerAccount>
 */
class LedgerAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => (string) fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->unique()->words(2, true),
            'type' => fake()->randomElement(['revenue', 'expense']),
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function revenue(): static
    {
        return $this->state(fn () => ['type' => 'revenue']);
    }

    public function expense(): static
    {
        return $this->state(fn () => ['type' => 'expense']);
    }
}

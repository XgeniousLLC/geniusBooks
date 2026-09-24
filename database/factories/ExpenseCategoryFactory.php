<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->randomElement(['Advertising', 'Software', 'Office', 'Travel', 'Utilities']),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}

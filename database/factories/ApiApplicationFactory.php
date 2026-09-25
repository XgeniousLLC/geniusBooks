<?php

namespace Database\Factories;

use App\Models\ApiApplication;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ApiApplication>
 */
class ApiApplicationFactory extends Factory
{
    protected $model = ApiApplication::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'name' => fake()->words(2, true).' app',
            'token_hash' => ApiApplication::hashToken(ApiApplication::generateToken()),
            'last_used_at' => null,
            'expires_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }
}

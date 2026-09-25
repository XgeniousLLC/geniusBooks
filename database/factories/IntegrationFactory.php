<?php

namespace Database\Factories;

use App\Enums\IntegrationProvider;
use App\Models\Company;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Integration>
 */
class IntegrationFactory extends Factory
{
    protected $model = Integration::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'provider' => fake()->randomElement(IntegrationProvider::cases())->value,
            'status' => 'connected',
            'access_token' => 'test_access_'.fake()->uuid(),
            'refresh_token' => null,
            'external_id' => null,
            'settings' => null,
            'last_sync_at' => null,
            'last_error' => null,
        ];
    }

    public function provider(IntegrationProvider $p): static
    {
        return $this->state(fn () => ['provider' => $p->value]);
    }
}

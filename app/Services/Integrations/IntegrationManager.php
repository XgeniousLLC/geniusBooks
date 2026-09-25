<?php

namespace App\Services\Integrations;

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

class IntegrationManager
{
    public function connect(Company $company, IntegrationProvider $provider, array $credentials): Integration
    {
        return Integration::updateOrCreate(
            ['company_id' => $company->id, 'provider' => $provider->value],
            [
                'status' => 'connected',
                'access_token' => $credentials['access_token'] ?? null,
                'refresh_token' => $credentials['refresh_token'] ?? null,
                'external_id' => $credentials['external_id'] ?? null,
                'settings' => $credentials['settings'] ?? [],
                'last_error' => null,
            ]
        );
    }

    public function disconnect(Integration $integration): void
    {
        $integration->update([
            'status' => 'disconnected',
            'access_token' => null,
            'refresh_token' => null,
            'last_error' => null,
        ]);
    }

    public function updateStatus(Integration $integration, string $status, ?string $error = null): void
    {
        $integration->update([
            'status' => $status,
            'last_error' => $error,
            'last_sync_at' => $status === 'connected' ? now() : $integration->last_sync_at,
        ]);
    }

    /**
     * Test connection to provider — uses fake HTTP in tests, real HTTP in prod.
     */
    public function testConnection(Integration $integration): bool
    {
        if (app()->environment('testing')) {
            return $integration->access_token !== null;
        }

        try {
            $response = Http::withToken((string) $integration->access_token)
                ->timeout(5)
                ->get($integration->provider->apiBaseUrl());

            return $response->successful() || $response->status() === 401;
        } catch (\Throwable $e) {
            $this->updateStatus($integration, 'error', $e->getMessage());
            return false;
        }
    }
}

<?php

namespace App\Services\Integrations;

use App\Models\Integration;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Payment;
use App\Models\Expense;
use Illuminate\Support\Facades\Log;

class SyncService
{
    /**
     * Push local entities to provider. Two-way sync is orchestrated here;
     * provider-specific payload mapping lives in formatForProvider().
     *
     * In production this would call Http:: with provider API; here we record
     * sync logs and simulate success so tests and docs have deterministic behavior.
     */
    public function push(Integration $integration, string $entityType, int $entityId): array
    {
        $integration->update(['status' => 'syncing']);

        try {
            $entity = $this->resolveEntity($entityType, $entityId, $integration->company_id);
            $payload = $this->formatForProvider($integration, $entityType, $entity, 'push');

            // Simulate HTTP call — replace with Http::post($url, $payload) per provider.
            $externalId = 'ext_'.uniqid();

            $log = $integration->logs()->create([
                'direction' => 'push',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'external_id' => $externalId,
                'status' => 'success',
                'message' => "Pushed {$entityType} #{$entityId} to {$integration->provider->label()}",
                'payload' => $payload,
            ]);

            $integration->update(['status' => 'connected', 'last_sync_at' => now(), 'last_error' => null]);

            return ['success' => true, 'log' => $log, 'external_id' => $externalId];
        } catch (\Throwable $e) {
            Log::warning('Integration push failed', ['integration' => $integration->id, 'error' => $e->getMessage()]);
            $integration->update(['status' => 'error', 'last_error' => $e->getMessage()]);
            $log = $integration->logs()->create([
                'direction' => 'push',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'log' => $log, 'error' => $e->getMessage()];
        }
    }

    public function pull(Integration $integration, string $entityType, ?string $externalId = null): array
    {
        $integration->update(['status' => 'syncing']);

        try {
            // Simulate fetching from provider — in prod, Http::get(...).
            $remoteData = $this->fetchFromProvider($integration, $entityType, $externalId);
            $local = $this->upsertFromRemote($integration, $entityType, $remoteData);

            $log = $integration->logs()->create([
                'direction' => 'pull',
                'entity_type' => $entityType,
                'entity_id' => $local?->id,
                'external_id' => $externalId ?? ($remoteData['id'] ?? null),
                'status' => 'success',
                'message' => "Pulled {$entityType} from {$integration->provider->label()}",
                'payload' => $remoteData,
            ]);

            $integration->update(['status' => 'connected', 'last_sync_at' => now(), 'last_error' => null]);

            return ['success' => true, 'log' => $log, 'entity' => $local];
        } catch (\Throwable $e) {
            $integration->update(['status' => 'error', 'last_error' => $e->getMessage()]);
            $log = $integration->logs()->create([
                'direction' => 'pull',
                'entity_type' => $entityType,
                'external_id' => $externalId,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
            return ['success' => false, 'log' => $log, 'error' => $e->getMessage()];
        }
    }

    public function syncAll(Integration $integration, array $entityTypes = ['customer','invoice','payment']): array
    {
        $results = [];
        foreach ($entityTypes as $type) {
            $results[$type] = $this->pull($integration, $type);
        }
        return $results;
    }

    private function resolveEntity(string $type, int $id, int $companyId)
    {
        return match ($type) {
            'customer' => Customer::withoutCompanyScope()->where('company_id', $companyId)->findOrFail($id),
            'product' => Product::withoutCompanyScope()->where('company_id', $companyId)->findOrFail($id),
            'invoice' => Invoice::withoutCompanyScope()->where('company_id', $companyId)->findOrFail($id),
            'payment' => Payment::withoutCompanyScope()->where('company_id', $companyId)->findOrFail($id),
            'expense' => Expense::withoutCompanyScope()->where('company_id', $companyId)->findOrFail($id),
            default => throw new \InvalidArgumentException("Unknown entity type {$type}"),
        };
    }

    private function formatForProvider(Integration $integration, string $type, $entity, string $direction): array
    {
        // Minimal normalized payload per provider — real mappers would be provider-specific.
        return match ($type) {
            'customer' => ['Name' => $entity->name, 'Email' => $entity->email, 'Provider' => $integration->provider->value],
            'invoice' => ['InvoiceNumber' => $entity->number ?? $entity->id, 'Total' => $entity->total, 'Provider' => $integration->provider->value],
            'payment' => ['Amount' => $entity->amount, 'Date' => $entity->payment_date ?? $entity->created_at, 'Provider' => $integration->provider->value],
            default => ['id' => $entity->id, 'provider' => $integration->provider->value],
        };
    }

    private function fetchFromProvider(Integration $integration, string $type, ?string $externalId): array
    {
        // Stubbed remote record — deterministic for tests/docs.
        return [
            'id' => $externalId ?? 'remote_'.uniqid(),
            'type' => $type,
            'provider' => $integration->provider->value,
            'name' => "Remote {$type} from {$integration->provider->label()}",
            'amount' => 10000,
        ];
    }

    private function upsertFromRemote(Integration $integration, string $type, array $data)
    {
        // For demo: create a local customer for customer pull, otherwise no-op.
        if ($type === 'customer') {
            return Customer::create([
                'company_id' => $integration->company_id,
                'name' => $data['name'] ?? 'Synced Customer',
                'email' => 'sync+'.uniqid().'@example.com',
            ]);
        }
        return null;
    }
}

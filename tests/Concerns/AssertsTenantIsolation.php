<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use PHPUnit\Framework\Assert;

/**
 * Reusable assertions for cross-tenant access attempts.
 *
 * Every tenant-facing module should register isolation cases here as it lands.
 */
trait AssertsTenantIsolation
{
    /**
     * Assert a user of one company cannot access a resource belonging to another.
     */
    protected function assertCrossTenantDenied(
        User $user,
        Company $sessionCompany,
        string $method,
        string $uri,
        array $data = [],
    ): void {
        $response = test()
            ->actingAs($user)
            ->withSession(['current_company_id' => $sessionCompany->id])
            ->json($method, $uri, $data);

        Assert::assertContains(
            $response->status(),
            [403, 404],
            "Expected cross-tenant access to {$method} {$uri} to be denied, got {$response->status()}."
        );
    }
}

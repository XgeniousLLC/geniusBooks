<?php

namespace App\Support;

/**
 * Request-scoped holder for the active tenant.
 *
 * Set once by the ResolveCurrentCompany middleware, then read by the
 * BelongsToCompany global scope and by authorization (spatie teams).
 */
class CompanyContext
{
    protected ?int $companyId = null;

    public function set(?int $companyId): void
    {
        $this->companyId = $companyId;

        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($companyId);
        }
    }

    public function id(): ?int
    {
        return $this->companyId;
    }

    public function has(): bool
    {
        return $this->companyId !== null;
    }

    public function forget(): void
    {
        $this->set(null);
    }
}

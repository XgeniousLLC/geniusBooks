<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes immutable audit entries. Failures are logged but never break the
 * originating request.
 */
class AuditLogger
{
    public function log(
        string $event,
        Model $subject,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        ?int $companyId = null,
    ): ?AuditLog {
        try {
            $request = app()->bound('request') ? request() : null;

            return AuditLog::create([
                'company_id' => $companyId
                    ?? $subject->company_id
                    ?? app(CompanyContext::class)->id(),
                'user_id' => auth()->guard('web')->id(),
                'event' => $event,
                'auditable_type' => $subject->getMorphClass(),
                'auditable_id' => $subject->getKey(),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'reason' => $reason,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Audit logging failed', [
                'event' => $event,
                'subject' => $subject->getMorphClass(),
                'id' => $subject->getKey(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}

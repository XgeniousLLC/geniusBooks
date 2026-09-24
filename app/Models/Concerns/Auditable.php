<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;

/**
 * Records create/update/delete events on the model into audit_logs.
 *
 * Sensitive and noisy attributes are excluded from the captured diff.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            app(AuditLogger::class)->log('created', $model, [], $model->auditAttributes());
        });

        static::updated(function ($model) {
            $changes = $model->auditFilter($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            app(AuditLogger::class)->log('updated', $model, $model->auditAttributes(), $changes);
        });

        static::deleted(function ($model) {
            app(AuditLogger::class)->log('deleted', $model, $model->auditAttributes(), []);
        });
    }

    /**
     * Current model attributes eligible for audit capture.
     */
    public function auditAttributes(): array
    {
        return $this->auditFilter($this->getAttributes());
    }

    /**
     * Strip hidden, sensitive and timestamp attributes from an attribute set.
     */
    public function auditFilter(array $attributes): array
    {
        $excluded = array_merge(
            $this->getHidden(),
            ['password', 'remember_token', 'created_at', 'updated_at', 'deleted_at']
        );

        return array_diff_key($attributes, array_flip($excluded));
    }
}

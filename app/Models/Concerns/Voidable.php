<?php

namespace App\Models\Concerns;

use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * Marks a financial record as voidable instead of destructible.
 *
 * Expects the model table to have: voided_at, voided_by, void_reason.
 */
trait Voidable
{
    protected static function bootVoidable(): void
    {
        static::deleting(function ($model) {
            if (! $model->isVoided()) {
                throw new RuntimeException(
                    'Financial records must be voided, not deleted. Call void() first.'
                );
            }
        });
    }

    public function void(string $reason, ?int $userId = null): static
    {
        if ($this->isVoided()) {
            return $this;
        }

        $this->forceFill([
            'voided_at' => now(),
            'voided_by' => $userId ?? auth()->id(),
            'void_reason' => $reason,
        ])->save();

        app(AuditLogger::class)->log('voided', $this, [], [
            'void_reason' => $reason,
            'voided_by' => $this->voided_by,
        ], $reason);

        return $this;
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function scopeNotVoided(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function scopeVoided(Builder $query): Builder
    {
        return $query->whereNotNull('voided_at');
    }
}

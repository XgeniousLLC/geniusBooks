<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Scopes a model to the active company and stamps company_id on create.
 *
 * When no company is active (console commands, platform admin) the scope is
 * a no-op so system operations can span tenants.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query) {
            $context = app(CompanyContext::class);

            if ($context->has()) {
                $query->where(
                    $query->getModel()->getTable().'.company_id',
                    $context->id()
                );
            }
        });

        static::creating(function ($model) {
            $context = app(CompanyContext::class);

            if ($context->has() && empty($model->company_id)) {
                $model->company_id = $context->id();
            }
        });

        static::saving(function ($model) {
            $context = app(CompanyContext::class);

            if (
                $context->has()
                && $model->company_id !== null
                && (int) $model->company_id !== (int) $context->id()
            ) {
                throw new RuntimeException(
                    'Attempted to persist a record for another company.'
                );
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope('company')->where('company_id', $companyId);
    }

    public function scopeWithoutCompanyScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('company');
    }
}

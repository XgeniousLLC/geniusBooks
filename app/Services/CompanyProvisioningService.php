<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Exceptions\LastOwnerException;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\LedgerAccount;
use App\Models\User;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Creates a company workspace and manages its membership + roles.
 */
class CompanyProvisioningService
{
    public const ROLE_OWNER = CompanyRole::Owner->value;

    public const ROLE_ACCOUNTANT = CompanyRole::Accountant->value;

    public const ROLE_STAFF = CompanyRole::Staff->value;

    public const ROLES = [
        self::ROLE_OWNER,
        self::ROLE_ACCOUNTANT,
        self::ROLE_STAFF,
    ];

    public function __construct(private readonly EmailTemplateService $emailTemplates) {}

    /**
     * Create a company owned by the given user and assign the owner role.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createFor(User $owner, array $attributes): Company
    {
        return DB::transaction(function () use ($owner, $attributes) {
            $company = Company::create($attributes);

            $company->users()->attach($owner->id, ['is_active' => true]);

            // Provisioning writes tenant-owned records (categories, chart of
            // accounts, templates); point the tenant context at the new company
            // so the tenant guard allows them, then restore it.
            $context = app(CompanyContext::class);
            $previous = $context->id();
            $context->set($company->id);

            try {
                $this->ensureRoles($company);
                $this->emailTemplates->ensureDefaults($company);
                $this->ensureExpenseCategories($company);
                $this->ensureChartOfAccounts($company);
                $this->assignRole($company, $owner, CompanyRole::Owner->value);
            } finally {
                $context->set($previous);
            }

            return $company;
        });
    }

    public function ensureChartOfAccounts(Company $company): void
    {
        $byCode = [];

        foreach (LedgerAccount::DEFAULTS as $definition) {
            $account = LedgerAccount::withoutCompanyScope()->firstOrCreate(
                ['company_id' => $company->id, 'code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'is_active' => true,
                ],
            );

            if (! empty($definition['parent']) && isset($byCode[$definition['parent']])) {
                $account->update(['parent_id' => $byCode[$definition['parent']]->id]);
            }

            $byCode[$definition['code']] = $account;
        }
    }

    public function ensureExpenseCategories(Company $company): void
    {
        foreach (ExpenseCategory::DEFAULTS as $name) {
            ExpenseCategory::withoutCompanyScope()->firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['is_default' => true, 'is_active' => true],
            );
        }
    }

    public function ensureRoles(Company $company): void
    {
        $this->withTeam($company->id, function () {
            foreach (self::ROLES as $role) {
                Role::findOrCreate($role, 'web');
            }
        });
    }

    public function assignRole(Company $company, User $user, string $role): void
    {
        $this->withTeam($company->id, function () use ($user, $role) {
            if (! $user->hasRole($role)) {
                $user->assignRole($role);
            }

            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function removeRole(Company $company, User $user, string $role): void
    {
        $this->withTeam($company->id, function () use ($user, $role) {
            $user->removeRole($role);
            $user->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    /**
     * Replace a member's role with exactly one role.
     */
    public function syncRole(Company $company, User $member, CompanyRole $role): void
    {
        if ($this->losesLastOwner($company, $member, $role)) {
            throw LastOwnerException::make();
        }

        $this->withTeam($company->id, function () use ($member, $role) {
            $member->syncRoles([$role->value]);
            $member->unsetRelation('roles')->unsetRelation('permissions');
        });
    }

    public function removeMember(Company $company, User $member): void
    {
        if ($this->isLastOwner($company, $member)) {
            throw LastOwnerException::make();
        }

        DB::transaction(function () use ($company, $member) {
            $this->withTeam($company->id, function () use ($member) {
                $member->syncRoles([]);
                $member->unsetRelation('roles')->unsetRelation('permissions');
            });
            $company->users()->detach($member->id);
        });
    }

    public function deactivateMember(Company $company, User $member): void
    {
        if ($this->isLastOwner($company, $member)) {
            throw LastOwnerException::make();
        }

        $company->users()->updateExistingPivot($member->id, ['is_active' => false]);
    }

    public function reactivateMember(Company $company, User $member): void
    {
        if (! $member->belongsToCompany($company->id) && ! $company->users()->whereKey($member->id)->exists()) {
            $company->users()->attach($member->id, ['is_active' => true]);

            return;
        }

        $company->users()->updateExistingPivot($member->id, ['is_active' => true]);
    }

    /**
     * Count active owners of a company.
     */
    public function ownerCount(Company $company): int
    {
        return $this->withTeam($company->id, function () use ($company) {
            return User::query()
                ->role(CompanyRole::Owner->value)
                ->where('users.is_active', true)
                ->whereHas('companies', function ($query) use ($company) {
                    $query->where('companies.id', $company->id)
                        ->where('company_user.is_active', true);
                })
                ->count();
        });
    }

    public function isLastOwner(Company $company, User $member): bool
    {
        $isOwner = $this->withTeam(
            $company->id,
            fn () => $member->hasRole(CompanyRole::Owner->value)
        );

        return $isOwner && $this->ownerCount($company) <= 1;
    }

    /**
     * The member's single role within a company, if any.
     */
    public function roleOf(Company $company, User $member): ?CompanyRole
    {
        return $this->withTeam($company->id, function () use ($member) {
            $member->unsetRelation('roles');
            $name = $member->getRoleNames()->first();

            return $name ? CompanyRole::fromName($name) : null;
        });
    }

    /**
     * Would demoting/removing this member leave the company without an owner?
     */
    private function losesLastOwner(Company $company, User $member, CompanyRole $target): bool
    {
        if ($target === CompanyRole::Owner) {
            return false;
        }

        return $this->isLastOwner($company, $member);
    }

    /**
     * Run a callback with the spatie permission team scoped to a company.
     */
    private function withTeam(int $companyId, callable $callback): mixed
    {
        $previous = getPermissionsTeamId();
        setPermissionsTeamId($companyId);

        try {
            return $callback();
        } finally {
            setPermissionsTeamId($previous);
        }
    }
}

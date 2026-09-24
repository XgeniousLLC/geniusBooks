<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function view(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company->id);
    }

    public function update(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company->id)
            && $user->can(Permission::ManageCompany);
    }

    public function manageUsers(User $user, Company $company): bool
    {
        return $user->belongsToCompany($company->id)
            && $user->can(Permission::ManageUsers);
    }
}

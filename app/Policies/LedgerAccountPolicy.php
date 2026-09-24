<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\LedgerAccount;
use App\Models\User;

class LedgerAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    public function view(User $user, LedgerAccount $account): bool
    {
        return $user->belongsToCompany($account->company_id)
            && $user->can(Permission::ManageFinances);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    public function update(User $user, LedgerAccount $account): bool
    {
        return $this->view($user, $account);
    }

    public function delete(User $user, LedgerAccount $account): bool
    {
        return $this->view($user, $account);
    }
}

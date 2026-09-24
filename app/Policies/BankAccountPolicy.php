<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    public function view(User $user, BankAccount $account): bool
    {
        return $user->belongsToCompany($account->company_id)
            && $user->can(Permission::ManageFinances);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    public function update(User $user, BankAccount $account): bool
    {
        return $this->view($user, $account);
    }

    public function delete(User $user, BankAccount $account): bool
    {
        return $this->view($user, $account);
    }
}

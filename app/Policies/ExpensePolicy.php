<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->belongsToCompany($expense->company_id)
            && $user->can(Permission::ManageExpenses);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function update(User $user, Expense $expense): bool
    {
        return $this->view($user, $expense) && ! $expense->isVoided();
    }

    public function void(User $user, Expense $expense): bool
    {
        return $user->belongsToCompany($expense->company_id)
            && $user->can(Permission::ManageFinances)
            && ! $expense->isVoided();
    }
}

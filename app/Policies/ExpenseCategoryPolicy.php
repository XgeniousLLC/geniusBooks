<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ExpenseCategory;
use App\Models\User;

class ExpenseCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function delete(User $user, ExpenseCategory $category): bool
    {
        return $user->belongsToCompany($category->company_id)
            && $user->can(Permission::ManageFinances);
    }
}

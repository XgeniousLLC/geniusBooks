<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CreditNote;
use App\Models\User;

class CreditNotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function view(User $user, CreditNote $creditNote): bool
    {
        return $user->belongsToCompany($creditNote->company_id)
            && $user->can(Permission::ManageSales);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }
}

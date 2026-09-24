<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    public function create(User $user): bool
    {
        return $user->can(Permission::ManageUsers);
    }

    public function delete(User $user, Invitation $invitation): bool
    {
        return $user->belongsToCompany($invitation->company_id)
            && $user->can(Permission::ManageUsers);
    }
}

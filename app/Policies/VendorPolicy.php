<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Vendor;

class VendorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function view(User $user, Vendor $vendor): bool
    {
        return $user->belongsToCompany($vendor->company_id)
            && $user->can(Permission::ManageExpenses);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageExpenses);
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $this->view($user, $vendor);
    }

    public function delete(User $user, Vendor $vendor): bool
    {
        return $this->view($user, $vendor);
    }
}

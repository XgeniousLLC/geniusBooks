<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->belongsToCompany($product->company_id)
            && $user->can(Permission::ManageSales);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function update(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }

    public function delete(User $user, Product $product): bool
    {
        return $this->view($user, $product);
    }
}

<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->belongsToCompany($quote->company_id)
            && $user->can(Permission::ManageSales);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function update(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->isEditable();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && $quote->status === \App\Enums\QuoteStatus::Draft->value;
    }

    public function convert(User $user, Quote $quote): bool
    {
        return $this->view($user, $quote) && ! $quote->isConverted();
    }
}

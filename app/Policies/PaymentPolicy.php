<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->belongsToCompany($payment->company_id)
            && $user->can(Permission::ManageSales);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $user->belongsToCompany($payment->company_id)
            && $user->can(Permission::ManageFinances)
            && ! $payment->isVoided();
    }
}

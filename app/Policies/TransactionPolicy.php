<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->belongsToCompany($transaction->company_id)
            && $user->can(Permission::ManageFinances);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageFinances);
    }

    /**
     * Only manual entries (adjustments, direct income) can be reversed here.
     * Payments and expenses are reversed through their own void actions.
     */
    public function reverse(User $user, Transaction $transaction): bool
    {
        return $this->view($user, $transaction)
            && in_array($transaction->type, [
                TransactionType::Adjustment->value,
                TransactionType::Income->value,
            ], true);
    }
}

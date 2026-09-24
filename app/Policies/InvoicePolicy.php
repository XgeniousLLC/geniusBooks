<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id)
            && $user->can(Permission::ManageSales);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageSales);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id)
            && $user->can(Permission::ManageFinances)
            && $invoice->isDraft();
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->belongsToCompany($invoice->company_id)
            && $user->can(Permission::ManageFinances)
            && ! $invoice->isCancelled();
    }
}

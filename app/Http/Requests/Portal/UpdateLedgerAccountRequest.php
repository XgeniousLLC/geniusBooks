<?php

namespace App\Http\Requests\Portal;

use App\Models\LedgerAccount;

class UpdateLedgerAccountRequest extends StoreLedgerAccountRequest
{
    public function authorize(): bool
    {
        $account = $this->route('account');

        return $account instanceof LedgerAccount
            && (bool) $this->user()?->can('update', $account);
    }
}

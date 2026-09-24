<?php

namespace App\Http\Requests\Portal;

use App\Models\Expense;

class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            && (bool) $this->user()?->can('update', $expense);
    }
}

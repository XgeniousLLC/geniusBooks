<?php

namespace App\Http\Requests\Portal;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', ExpenseCategory::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(\App\Support\CompanyContext::class)->id();

        return [
            'name' => [
                'required', 'string', 'max:100',
                \Illuminate\Validation\Rule::unique('expense_categories', 'name')
                    ->where('company_id', $companyId),
            ],
        ];
    }
}

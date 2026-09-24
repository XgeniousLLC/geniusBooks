<?php

namespace App\Http\Requests\Portal;

use App\Models\Expense;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Expense::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'vendor_id' => ['nullable', Rule::exists('vendors', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'is_recurring' => ['boolean'],
            'recurrence_interval' => ['nullable', Rule::in(Expense::INTERVALS), 'required_if:is_recurring,true'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_recurring' => $this->boolean('is_recurring', false),
            'tax_amount' => $this->input('tax_amount') === '' ? null : $this->input('tax_amount'),
        ]);
    }
}

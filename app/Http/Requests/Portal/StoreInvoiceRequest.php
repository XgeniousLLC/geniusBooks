<?php

namespace App\Http\Requests\Portal;

use App\Models\Invoice;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('company_id', $companyId),
            ],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'discount_type' => ['nullable', Rule::in(['percent', 'fixed'])],
            'discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:discount_type'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('products', 'id')->where('company_id', $companyId),
            ],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', Rule::in(['percent', 'fixed'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0', 'required_with:items.*.discount_type'],
            'items.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'discount_type' => $this->input('discount_type') ?: null,
            'discount_value' => $this->input('discount_value') === '' ? null : $this->input('discount_value'),
        ]);
    }
}

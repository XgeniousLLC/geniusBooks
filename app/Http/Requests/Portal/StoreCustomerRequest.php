<?php

namespace App\Http\Requests\Portal;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:2000'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            'payment_terms_days' => ['nullable', 'integer', 'between:0,365'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}

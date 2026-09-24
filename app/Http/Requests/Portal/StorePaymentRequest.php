<?php

namespace App\Http\Requests\Portal;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', Payment::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'bank_account_id' => ['required', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
            'send_receipt' => ['boolean'],
            'allocations' => ['nullable', 'array'],
            'allocations.*.invoice_id' => ['required', Rule::exists('invoices', 'id')->where('company_id', $companyId)],
            'allocations.*.amount' => ['required', 'numeric', 'min:0'],
        ];
    }
}

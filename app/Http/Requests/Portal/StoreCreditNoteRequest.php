<?php

namespace App\Http\Requests\Portal;

use App\Models\CreditNote;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', CreditNote::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();

        return [
            'issue_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
            'bank_account_id' => ['nullable', Rule::exists('bank_accounts', 'id')->where('company_id', $companyId)],
        ];
    }
}

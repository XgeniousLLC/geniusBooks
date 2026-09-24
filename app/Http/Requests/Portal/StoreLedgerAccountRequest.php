<?php

namespace App\Http\Requests\Portal;

use App\Enums\LedgerAccountType;
use App\Models\LedgerAccount;
use App\Support\CompanyContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('create', LedgerAccount::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $companyId = app(CompanyContext::class)->id();
        $account = $this->route('account');

        $codeRule = Rule::unique('ledger_accounts', 'code')->where('company_id', $companyId);
        if ($account instanceof LedgerAccount) {
            $codeRule->ignore($account->id);
        }

        return [
            'code' => ['nullable', 'string', 'max:20', $codeRule],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(LedgerAccountType::values())],
            'parent_id' => ['nullable', Rule::exists('ledger_accounts', 'id')->where('company_id', $companyId)],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }
}

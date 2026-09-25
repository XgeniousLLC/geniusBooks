<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        $query = Transaction::with(['bankAccount','ledgerAccount'])->latest();
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        return response()->json($query->paginate($perPage));
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction->load(['bankAccount','ledgerAccount']));
    }

    public function transfer(Request $request)
    {
        $data = $request->validate([
            'from_account_id' => ['required','exists:bank_accounts,id'],
            'to_account_id' => ['required','exists:bank_accounts,id','different:from_account_id'],
            'amount' => ['required','integer','min:1'],
            'date' => ['required','date'],
            'description' => ['nullable','string','max:1000'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $from = \App\Models\BankAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['from_account_id']);
        $to = \App\Models\BankAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['to_account_id']);

        $result = app(\App\Services\Accounting\LedgerPostingService::class)->transfer(
            $company, $from, $to, $data['amount'], $data['description'] ?? 'Transfer', $data['date']
        );

        return response()->json($result, 201);
    }

    public function income(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => ['required','exists:bank_accounts,id'],
            'ledger_account_id' => ['required','exists:ledger_accounts,id'],
            'amount' => ['required','integer','min:1'],
            'date' => ['required','date'],
            'description' => ['nullable','string','max:1000'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $account = \App\Models\BankAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['bank_account_id']);
        $ledger = \App\Models\LedgerAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['ledger_account_id']);

        $tx = app(\App\Services\Accounting\LedgerPostingService::class)->in(
            $company, $account, $data['amount'], \App\Enums\TransactionType::Income->value, $data['description'] ?? 'Income', $data['date'], null, null, $ledger
        );

        return response()->json($tx, 201);
    }

    public function adjustment(Request $request)
    {
        $data = $request->validate([
            'bank_account_id' => ['required','exists:bank_accounts,id'],
            'amount' => ['required','integer'],
            'date' => ['required','date'],
            'description' => ['required','string','max:1000'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $account = \App\Models\BankAccount::withoutCompanyScope()->where('company_id', $company->id)->findOrFail($data['bank_account_id']);
        $dir = $data['amount'] >= 0 ? \App\Enums\TransactionDirection::In : \App\Enums\TransactionDirection::Out;

        $tx = app(\App\Services\Accounting\LedgerPostingService::class)->post($company, [
            'bank_account_id' => $account->id,
            'type' => \App\Enums\TransactionType::Adjustment->value,
            'direction' => $dir->value,
            'amount' => abs($data['amount']),
            'currency' => $account->currency ?? $company->currency,
            'description' => $data['description'],
            'occurred_on' => $data['date'],
        ]);

        return response()->json($tx, 201);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        return response()->json(Expense::with(['category','vendor','account'])->latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'expense_category_id' => ['required','exists:expense_categories,id'],
            'vendor_id' => ['nullable','exists:vendors,id'],
            'bank_account_id' => ['required','exists:bank_accounts,id'],
            'amount' => ['required','integer','min:1'],
            'tax_amount' => ['nullable','integer','min:0'],
            'date' => ['required','date'],
            'description' => ['required','string','max:1000'],
            'reference' => ['nullable','string','max:255'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $expense = app(\App\Services\Accounting\ExpenseService::class)->record($company, $data);

        return response()->json($expense->load(['category','vendor']), 201);
    }

    public function show(Expense $expense)
    {
        return response()->json($expense->load(['category','vendor','account']));
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $request->validate([
            'description' => ['sometimes','string','max:1000'],
            'amount' => ['sometimes','integer','min:1'],
        ]);
        $expense->update($data);
        return response()->json($expense->fresh()->load(['category','vendor']));
    }

    public function void(Request $request, Expense $expense)
    {
        $data = $request->validate(['reason' => ['required','string','max:500']]);
        app(\App\Services\Accounting\ExpenseService::class)->void($expense, $data['reason']);
        return response()->json($expense->fresh());
    }
}

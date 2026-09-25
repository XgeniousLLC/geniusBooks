<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        return response()->json(Payment::with(['customer','allocations'])->latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required','exists:customers,id'],
            'bank_account_id' => ['required','exists:bank_accounts,id'],
            'amount' => ['required','integer','min:1'],
            'date' => ['required','date'],
            'method' => ['required','string','max:50'],
            'reference' => ['nullable','string','max:255'],
            'allocations' => ['sometimes','array'],
            'allocations.*.invoice_id' => ['required','exists:invoices,id'],
            'allocations.*.amount' => ['required','integer','min:1'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $payment = app(\App\Services\Accounting\PaymentService::class)->record($company, $data);

        return response()->json($payment->load(['customer','allocations']), 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load(['customer','allocations','account']));
    }

    public function void(Request $request, Payment $payment)
    {
        $data = $request->validate(['reason' => ['required','string','max:500']]);
        app(\App\Services\Accounting\PaymentService::class)->void($payment, $data['reason']);
        return response()->json($payment->fresh()->load(['allocations']));
    }
}

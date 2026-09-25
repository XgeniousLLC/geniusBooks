<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        return response()->json(Quote::with(['customer','items'])->latest()->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required','exists:customers,id'],
            'issue_date' => ['required','date'],
            'expiry_date' => ['nullable','date','after_or_equal:issue_date'],
            'notes' => ['nullable','string','max:5000'],
            'terms' => ['nullable','string','max:5000'],
            'items' => ['required','array','min:1'],
            'items.*.product_id' => ['nullable','exists:products,id'],
            'items.*.description' => ['required','string','max:500'],
            'items.*.quantity' => ['required','integer','min:1'],
            'items.*.unit_price' => ['required','numeric','min:0'],
            'items.*.tax_rate' => ['nullable','numeric','min:0','max:100'],
            'items.*.discount_type' => ['nullable','in:percent,fixed'],
            'items.*.discount_value' => ['nullable','numeric','min:0'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $quote = app(\App\Services\Invoicing\QuoteService::class)->create($company, $data);

        return response()->json($quote->load(['customer','items']), 201);
    }

    public function show(Quote $quote)
    {
        return response()->json($quote->load(['customer','items']));
    }

    public function update(Request $request, Quote $quote)
    {
        $data = $request->validate([
            'customer_id' => ['sometimes','exists:customers,id'],
            'issue_date' => ['sometimes','date'],
            'expiry_date' => ['nullable','date'],
            'notes' => ['nullable','string','max:5000'],
            'terms' => ['nullable','string','max:5000'],
        ]);
        $quote->update($data);
        return response()->json($quote->fresh()->load(['customer','items']));
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();
        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        $query = Invoice::with(['customer','items'])->latest();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['required','exists:customers,id'],
            'issue_date' => ['required','date'],
            'due_date' => ['required','date','after_or_equal:issue_date'],
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
            'discount_type' => ['nullable','in:percent,fixed'],
            'discount_value' => ['nullable','numeric','min:0'],
        ]);

        $company = \App\Models\Company::withoutGlobalScope('company')->findOrFail(app(\App\Support\CompanyContext::class)->id());
        $invoice = app(\App\Services\Invoicing\InvoiceService::class)->create($company, $data);

        return response()->json($invoice->load(['customer','items']), 201);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['customer','items','payments']));
    }

    public function update(Request $request, Invoice $invoice)
    {
        // Only draft invoices can be edited — delegate to service for totals.
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be updated.'], 422);
        }

        $data = $request->validate([
            'customer_id' => ['sometimes','exists:customers,id'],
            'issue_date' => ['sometimes','date'],
            'due_date' => ['sometimes','date'],
            'notes' => ['nullable','string','max:5000'],
            'terms' => ['nullable','string','max:5000'],
            'items' => ['sometimes','array','min:1'],
            'items.*.product_id' => ['nullable','exists:products,id'],
            'items.*.description' => ['required','string','max:500'],
            'items.*.quantity' => ['required','integer','min:1'],
            'items.*.unit_price' => ['required','numeric','min:0'],
            'items.*.tax_rate' => ['nullable','numeric','min:0','max:100'],
            'items.*.discount_type' => ['nullable','in:percent,fixed'],
            'items.*.discount_value' => ['nullable','numeric','min:0'],
        ]);

        $invoice = app(\App\Services\Invoicing\InvoiceService::class)->update($invoice, $data);

        return response()->json($invoice->load(['customer','items']));
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be deleted.'], 422);
        }
        $invoice->delete();
        return response()->json(null, 204);
    }
}

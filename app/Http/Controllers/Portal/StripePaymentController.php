<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Accounting\PaymentService;
use App\Services\Payments\StripeGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class StripePaymentController extends Controller
{
    public function __construct(
        private readonly StripeGateway $stripe,
        private readonly PaymentService $payments,
    ) {}

    /**
     * Start a Stripe Checkout session for an invoice (reachable via its signed
     * public link).
     */
    public function pay(Invoice $invoice): RedirectResponse
    {
        $invoice->loadMissing('company');
        $company = $invoice->company;

        if (! $this->stripe->isConfigured($company)) {
            return back()->with('error', 'Online payment is not available for this invoice.');
        }

        if ($invoice->isCancelled() || $invoice->balance() <= 0) {
            return back()->with('error', 'This invoice has no outstanding balance.');
        }

        $publicUrl = app(\App\Services\Invoicing\InvoiceDeliveryService::class)->publicUrl($invoice);

        try {
            $url = $this->stripe->createCheckoutSession($company, $invoice, $publicUrl, $publicUrl);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($url);
    }

    /**
     * Stripe webhook: verify the signature and record the payment.
     */
    public function webhook(Request $request): Response
    {
        $payload = $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        $event = json_decode($payload, true);
        $companyId = (int) data_get($event, 'data.object.metadata.company_id');

        if (! $companyId) {
            return response('Missing company reference.', 400);
        }

        $company = Company::find($companyId);

        if (! $company || empty($company->stripe_webhook_secret)) {
            return response('Unknown company.', 400);
        }

        try {
            $verified = $this->stripe->verifyWebhook($payload, $signature, (string) $company->stripe_webhook_secret);
        } catch (RuntimeException $e) {
            return response($e->getMessage(), 400);
        }

        if (($verified['type'] ?? '') === 'checkout.session.completed') {
            $this->recordPayment($company, $verified['data']['object']);
        }

        return response()->noContent();
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function recordPayment(Company $company, array $session): void
    {
        $invoiceId = (int) ($session['metadata']['invoice_id'] ?? 0);
        $amount = (int) ($session['amount_total'] ?? 0);

        if ($invoiceId <= 0 || $amount <= 0) {
            return;
        }

        $invoice = Invoice::withoutCompanyScope()
            ->where('company_id', $company->id)
            ->find($invoiceId);

        if (! $invoice || $invoice->isCancelled()) {
            return;
        }

        $account = BankAccount::withoutCompanyScope()->find($company->stripe_deposit_account_id);

        if (! $account) {
            return;
        }

        $reference = $session['payment_intent'] ?? $session['id'] ?? null;
        $key = 'stripe_'.($session['id'] ?? $invoice->id);

        $this->payments->record($company, [
            'customer_id' => $invoice->customer_id,
            'bank_account_id' => $account->id,
            'date' => now()->toDateString(),
            'amount' => $amount,
            'method' => 'card',
            'reference' => is_string($reference) ? $reference : null,
            'idempotency_key' => $key,
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $amount]],
        ]);
    }
}

<?php

namespace App\Services\Payments;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Stripe integration: creates Checkout Sessions for invoices and
 * verifies webhook signatures. Uses the HTTP client directly (no SDK), so it
 * is easy to fake in tests.
 */
class StripeGateway
{
    private const API = 'https://api.stripe.com/v1';

    public function isConfigured(Company $company): bool
    {
        return (bool) $company->online_payments_enabled
            && ! empty($company->stripe_secret_key)
            && ! empty($company->stripe_deposit_account_id);
    }

    public function createCheckoutSession(Company $company, Invoice $invoice, string $successUrl, string $cancelUrl): string
    {
        if (! $this->isConfigured($company)) {
            throw new RuntimeException('Online payments are not configured for this business.');
        }

        $amount = $invoice->balance();

        if ($amount <= 0) {
            throw new RuntimeException('This invoice has no outstanding balance.');
        }

        $response = Http::asForm()
            ->withToken($company->stripe_secret_key)
            ->post(self::API.'/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'client_reference_id' => (string) $invoice->id,
                'metadata[invoice_id]' => (string) $invoice->id,
                'metadata[company_id]' => (string) $company->id,
                'line_items[0][quantity]' => 1,
                'line_items[0][price_data][currency]' => strtolower($invoice->currency),
                'line_items[0][price_data][unit_amount]' => $amount,
                'line_items[0][price_data][product_data][name]' => 'Invoice '.$invoice->number,
            ])
            ->throw()
            ->json();

        $url = $response['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe did not return a checkout URL.');
        }

        return $url;
    }

    /**
     * Verify a Stripe webhook signature and return the decoded event.
     *
     * @return array<string, mixed>
     */
    public function verifyWebhook(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): array
    {
        if ($secret === '' || $signatureHeader === '') {
            throw new RuntimeException('Missing Stripe webhook secret or signature.');
        }

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
            if ($key === 't') {
                $timestamp = (int) $value;
            } elseif ($key === 'v1') {
                $signatures[] = (string) $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new RuntimeException('Malformed Stripe signature header.');
        }

        if (abs(time() - $timestamp) > $tolerance) {
            throw new RuntimeException('Stripe webhook timestamp outside tolerance.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $event = json_decode($payload, true);

                if (! is_array($event)) {
                    throw new RuntimeException('Invalid Stripe webhook payload.');
                }

                return $event;
            }
        }

        throw new RuntimeException('Stripe webhook signature verification failed.');
    }
}

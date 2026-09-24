<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

function stripeCompany(): array
{
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 0]);

    $company->update([
        'online_payments_enabled' => true,
        'stripe_secret_key' => 'sk_test_123',
        'stripe_webhook_secret' => 'whsec_test',
        'stripe_deposit_account_id' => $account->id,
    ]);

    $customer = Customer::factory()->for($company)->create();

    return [$user, $company->fresh(), $account, $customer];
}

it('creates a stripe checkout session and redirects the customer', function () {
    Http::fake(['api.stripe.com/*' => Http::response(['id' => 'cs_1', 'url' => 'https://checkout.stripe.test/cs_1'])]);

    [, $company, , $customer] = stripeCompany();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 50000, 'subtotal' => 50000]);

    $url = URL::temporarySignedRoute('portal.invoices.public.pay', now()->addDays(30), ['invoice' => $invoice->id]);

    $this->get($url)->assertRedirect('https://checkout.stripe.test/cs_1');

    Http::assertSent(fn ($request) => str_contains($request->url(), 'api.stripe.com/v1/checkout/sessions')
        && $request['line_items[0][price_data][unit_amount]'] === 50000
        && $request['metadata[invoice_id]'] === (string) $invoice->id);
});

it('will not start a payment when online payments are disabled', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 50000, 'subtotal' => 50000]);

    $url = URL::temporarySignedRoute('portal.invoices.public.pay', now()->addDays(30), ['invoice' => $invoice->id]);

    $this->get($url)->assertRedirect()->assertSessionHas('error');
});

it('records a payment from a valid stripe webhook', function () {
    [, $company, $account, $customer] = stripeCompany();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 50000, 'subtotal' => 50000]);

    stripeWebhookResponse($company, $invoice, 50000, 'cs_test_1')->assertNoContent();

    expect($invoice->fresh()->amount_paid)->toBe(50000)
        ->and(Payment::where('company_id', $company->id)->count())->toBe(1)
        ->and($account->balance())->toBe(50000);
});

it('is idempotent for repeated webhook deliveries', function () {
    [, $company, , $customer] = stripeCompany();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 50000, 'subtotal' => 50000]);

    stripeWebhookResponse($company, $invoice, 50000, 'cs_test_dup');
    stripeWebhookResponse($company, $invoice, 50000, 'cs_test_dup');

    expect(Payment::where('company_id', $company->id)->count())->toBe(1);
});

it('rejects a webhook with an invalid signature', function () {
    [, $company, , $customer] = stripeCompany();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 50000, 'subtotal' => 50000]);

    $payload = json_encode([
        'type' => 'checkout.session.completed',
        'data' => ['object' => ['id' => 'cs_bad', 'amount_total' => 50000, 'metadata' => [
            'invoice_id' => (string) $invoice->id, 'company_id' => (string) $company->id,
        ]]],
    ]);

    $response = $this->call('POST', '/portal/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't='.time().',v1=deadbeef',
    ], $payload);

    $response->assertStatus(400);
    expect(Payment::count())->toBe(0);
});

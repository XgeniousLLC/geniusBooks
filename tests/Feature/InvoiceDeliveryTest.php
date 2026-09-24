<?php

use App\Enums\InvoiceStatus;
use App\Mail\InvoiceMail;
use App\Models\Customer;
use App\Models\EmailLog;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('downloads an invoice pdf', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = Invoice::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->get("/portal/invoices/{$invoice->id}/pdf")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('emails an invoice, logs it and marks a draft as sent', function () {
    Mail::fake();

    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create(['email' => 'billing@acme.test']);
    $invoice = Invoice::factory()->for($company)->for($customer)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/email")->assertRedirect();

    Mail::assertQueued(InvoiceMail::class, fn ($mail) => $mail->hasTo('billing@acme.test'));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent->value)
        ->and(EmailLog::where('company_id', $company->id)->where('to_email', 'billing@acme.test')->exists())->toBeTrue();
});

it('refuses to email when the customer has no address', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create(['email' => null]);
    $invoice = Invoice::factory()->for($company)->for($customer)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/email")
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('renders a public invoice through a signed link and records the view', function () {
    $company = \App\Models\Company::factory()->create();
    $invoice = Invoice::factory()->for($company)->sent()->create();

    $url = URL::temporarySignedRoute('portal.invoices.public', now()->addDays(30), ['invoice' => $invoice->id]);

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Invoices/Public'));

    expect($invoice->fresh()->viewed_at)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Viewed->value);
});

it('rejects an unsigned public invoice link', function () {
    $company = \App\Models\Company::factory()->create();
    $invoice = Invoice::factory()->for($company)->sent()->create();

    $this->get("/portal/invoices/public/{$invoice->id}")->assertForbidden();
});

it('downloads a public invoice pdf through a signed link', function () {
    $company = \App\Models\Company::factory()->create();
    $invoice = Invoice::factory()->for($company)->create();

    $url = URL::temporarySignedRoute('portal.invoices.public.pdf', now()->addDays(30), ['invoice' => $invoice->id]);

    $this->get($url)
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Sms\SmsManager;
use Illuminate\Support\Facades\Mail;

it('sends an sms with the invoice when the company enables it', function () {
    Mail::fake();
    $fake = SmsManager::fake();

    [$user, $company] = userWithCompany('owner', ['sms_notifications_enabled' => true]);
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test', 'phone' => '+8801700000000']);
    $invoice = Invoice::factory()->for($company)->for($customer)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/email")->assertRedirect();

    $fake->assertSent(fn ($message) => $message['to'] === '+8801700000000' && str_contains($message['message'], $invoice->number));
});

it('does not send an sms when the company has it disabled', function () {
    Mail::fake();
    $fake = SmsManager::fake();

    [$user, $company] = userWithCompany('owner', ['sms_notifications_enabled' => false]);
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test', 'phone' => '+8801700000000']);
    $invoice = Invoice::factory()->for($company)->for($customer)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/email")->assertRedirect();

    $fake->assertNothingSent();
});

it('sends a test sms from settings', function () {
    $fake = SmsManager::fake();

    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->post('/portal/settings/email/sms-test', ['phone' => '+8801700000000'])->assertRedirect();

    $fake->assertSent(fn ($message) => $message['to'] === '+8801700000000');
});

it('persists the sms notification toggle', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->patch('/portal/settings/email', [
        'email_from_name' => 'Acme',
        'email_reply_to' => 'billing@acme.test',
        'reminders_enabled' => true,
        'reminder_days_before' => 3,
        'reminder_days_overdue' => 1,
        'sms_notifications_enabled' => true,
    ])->assertRedirect();

    expect($company->fresh()->sms_notifications_enabled)->toBeTrue();
});

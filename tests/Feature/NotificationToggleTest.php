<?php

use App\Mail\PaymentReceivedMail;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\Mail;

it('persists notification toggles', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->patch('/portal/settings/email', [
        'email_from_name' => 'Acme',
        'email_reply_to' => 'billing@acme.test',
        'payment_instructions' => '',
        'invoice_footer' => '',
        'reminders_enabled' => true,
        'reminder_days_before' => 3,
        'reminder_days_overdue' => 1,
        'notify_invoice_sent' => false,
        'notify_payment_received' => false,
        'notify_invoice_due' => false,
        'notify_invoice_overdue' => true,
    ])->assertRedirect();

    $company->refresh();

    expect($company->notify_invoice_sent)->toBeFalse()
        ->and($company->notify_payment_received)->toBeFalse()
        ->and($company->notify_invoice_due)->toBeFalse()
        ->and($company->notify_invoice_overdue)->toBeTrue();
});

it('blocks invoice emails when the notification is disabled', function () {
    Mail::fake();

    [$user, $company] = userWithCompany('owner', ['notify_invoice_sent' => false]);
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test']);
    $invoice = Invoice::factory()->for($company)->for($customer)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/email")
        ->assertRedirect()
        ->assertSessionHas('error');

    Mail::assertNothingQueued();
});

it('blocks payment receipts when disabled and skips auto-send', function () {
    Mail::fake();

    [$user, $company] = userWithCompany('owner', ['notify_payment_received' => false]);
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test']);
    $account = BankAccount::factory()->for($company)->create();
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 10000, 'subtotal' => 10000,
    ]);

    actingAsCompany($user, $company);

    // Auto-send on record is skipped when disabled.
    $this->post('/portal/payments', [
        'customer_id' => $customer->id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'method' => 'cash',
        'send_receipt' => true,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
    ]);

    Mail::assertNotQueued(PaymentReceivedMail::class);

    $payment = Payment::first();
    $this->post("/portal/payments/{$payment->id}/email")
        ->assertRedirect()
        ->assertSessionHas('error');
});

it('suppresses due reminders when the notification is disabled', function () {
    Mail::fake();

    $company = \App\Models\Company::factory()->create([
        'reminders_enabled' => true,
        'reminder_days_before' => 3,
        'notify_invoice_due' => false,
    ]);
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test']);
    $invoice = Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 10000, 'amount_paid' => 0, 'due_date' => now()->addDays(1)->toDateString(),
    ]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertNothingQueued();
    expect($invoice->fresh()->due_reminder_sent_at)->toBeNull();
});

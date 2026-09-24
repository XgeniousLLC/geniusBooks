<?php

use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Support\Facades\Mail;

function reminderInvoice(Company $company, array $attributes = []): Invoice
{
    $customer = Customer::factory()->for($company)->create(['email' => 'ap@acme.test']);

    return Invoice::factory()->for($company)->for($customer)->sent()->create(array_merge([
        'total' => 10000,
        'amount_paid' => 0,
    ], $attributes));
}

it('queues a due reminder within the configured window', function () {
    Mail::fake();

    $company = Company::factory()->create(['reminders_enabled' => true, 'reminder_days_before' => 3]);
    $invoice = reminderInvoice($company, ['due_date' => now()->addDays(2)->toDateString()]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertQueued(InvoiceMail::class, 1);
    expect($invoice->fresh()->due_reminder_sent_at)->not->toBeNull();
});

it('skips invoices outside the reminder window', function () {
    Mail::fake();

    $company = Company::factory()->create(['reminders_enabled' => true, 'reminder_days_before' => 3]);
    $invoice = reminderInvoice($company, ['due_date' => now()->addDays(20)->toDateString()]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertQueued(InvoiceMail::class, 0);
    expect($invoice->fresh()->due_reminder_sent_at)->toBeNull();
});

it('queues an overdue reminder', function () {
    Mail::fake();

    $company = Company::factory()->create(['reminders_enabled' => true, 'reminder_days_overdue' => 1]);
    $invoice = reminderInvoice($company, ['due_date' => now()->subDays(3)->toDateString()]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertQueued(InvoiceMail::class, 1);
    expect($invoice->fresh()->overdue_reminder_sent_at)->not->toBeNull();
});

it('respects the reminders toggle', function () {
    Mail::fake();

    $company = Company::factory()->create(['reminders_enabled' => false, 'reminder_days_before' => 3]);
    $invoice = reminderInvoice($company, ['due_date' => now()->addDays(1)->toDateString()]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertQueued(InvoiceMail::class, 0);
    expect($invoice->fresh()->due_reminder_sent_at)->toBeNull();
});

it('does not remind paid invoices', function () {
    Mail::fake();

    $company = Company::factory()->create(['reminders_enabled' => true, 'reminder_days_before' => 3]);
    $invoice = reminderInvoice($company, [
        'due_date' => now()->addDays(1)->toDateString(),
        'total' => 10000,
        'amount_paid' => 10000,
    ]);

    $this->artisan('invoices:send-reminders')->assertExitCode(0);

    Mail::assertQueued(InvoiceMail::class, 0);
});

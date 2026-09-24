<?php

use App\Enums\InvoiceStatus;
use App\Mail\PaymentReceivedMail;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Mail;

function openInvoice($company, int $total, ?Customer $customer = null): Invoice
{
    $customer ??= Customer::factory()->for($company)->create(['email' => 'ap@acme.test']);

    return Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => $total,
        'subtotal' => $total,
    ]);
}

it('records a partial payment, posts the ledger and updates the invoice', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 100000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '400.00',
        'method' => 'bank_transfer',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '400.00']],
    ])->assertRedirect();

    $payment = Payment::first();

    expect($invoice->fresh()->amount_paid)->toBe(40000)
        ->and($invoice->fresh()->displayStatus())->toBe(InvoiceStatus::PartiallyPaid)
        ->and($payment->allocations()->sum('amount'))->toBe(40000)
        ->and(Transaction::where('type', 'payment')->where('direction', 'in')->sum('amount'))->toBe(40000);
});

it('marks an invoice paid on full payment', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 50000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '500.00',
        'method' => 'cash',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '500.00']],
    ]);

    expect($invoice->fresh()->displayStatus())->toBe(InvoiceStatus::Paid);
});

it('treats overpayment as customer credit', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 10000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '150.00',
        'method' => 'bank_transfer',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
    ]);

    $payment = Payment::first();

    expect($payment->unapplied())->toBe(5000)
        ->and(app(\App\Services\Accounting\PaymentService::class)->customerCredit($invoice->customer))->toBe(5000)
        ->and($invoice->fresh()->displayStatus())->toBe(InvoiceStatus::Paid);
});

it('is idempotent for repeated submissions', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 10000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $payload = [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'method' => 'cash',
        'idempotency_key' => 'fixed-key-123',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
    ];

    $this->post('/portal/payments', $payload);
    $this->post('/portal/payments', $payload);

    expect(Payment::count())->toBe(1)
        ->and($invoice->fresh()->amount_paid)->toBe(10000);
});

it('allocates one payment across multiple invoices', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();
    $first = openInvoice($company, 5000, $customer);
    $second = openInvoice($company, 5000, $customer);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $customer->id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'method' => 'bank_transfer',
        'allocations' => [
            ['invoice_id' => $first->id, 'amount' => '50.00'],
            ['invoice_id' => $second->id, 'amount' => '50.00'],
        ],
    ]);

    expect($first->fresh()->displayStatus())->toBe(InvoiceStatus::Paid)
        ->and($second->fresh()->displayStatus())->toBe(InvoiceStatus::Paid);
});

it('voids a payment and reverses the ledger and invoice', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 10000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'method' => 'cash',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
    ]);

    $payment = Payment::first();

    $this->post("/portal/payments/{$payment->id}/void", ['reason' => 'Duplicate'])->assertRedirect();

    expect($payment->fresh()->isVoided())->toBeTrue()
        ->and($invoice->fresh()->amount_paid)->toBe(0)
        ->and($account->balance())->toBe(0);
});

it('rejects allocations exceeding the payment amount', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 10000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '50.00',
        'method' => 'cash',
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '80.00']],
    ])->assertSessionHasErrors('allocations');

    expect(Payment::count())->toBe(0);
});

it('queues a receipt when requested', function () {
    Mail::fake();

    [$user, $company] = userWithCompany('owner');
    $invoice = openInvoice($company, 10000);
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/payments', [
        'customer_id' => $invoice->customer_id,
        'bank_account_id' => $account->id,
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'method' => 'cash',
        'send_receipt' => true,
        'allocations' => [['invoice_id' => $invoice->id, 'amount' => '100.00']],
    ]);

    Mail::assertQueued(PaymentReceivedMail::class, fn ($mail) => $mail->hasTo('ap@acme.test'));
});

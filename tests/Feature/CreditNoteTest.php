<?php

use App\Models\BankAccount;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;

function creditInvoice($company, int $total = 100000): Invoice
{
    $customer = Customer::factory()->for($company)->create();

    return Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => $total,
        'subtotal' => $total,
    ]);
}

it('issues a credit note that reduces the invoice balance', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = creditInvoice($company);

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/credit-notes", [
        'issue_date' => now()->toDateString(),
        'amount' => '300.00',
        'reason' => 'Goodwill',
    ])->assertRedirect();

    $credit = CreditNote::first();

    expect($credit->number)->toBe('CN-0001')
        ->and($credit->status)->toBe(CreditNote::STATUS_APPLIED)
        ->and($invoice->fresh()->credit_total)->toBe(30000)
        ->and($invoice->fresh()->balance())->toBe(70000);
});

it('refunds a credit note from a bank account', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = creditInvoice($company);
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 50000]);

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/credit-notes", [
        'issue_date' => now()->toDateString(),
        'amount' => '200.00',
        'bank_account_id' => $account->id,
    ])->assertRedirect();

    $credit = CreditNote::first();

    expect($credit->status)->toBe(CreditNote::STATUS_REFUNDED)
        ->and($account->balance())->toBe(30000)
        ->and(Transaction::where('type', 'refund')->where('direction', 'out')->sum('amount'))->toBe(20000);
});

it('rejects a credit larger than the balance', function () {
    [$user, $company] = userWithCompany('owner');
    $invoice = creditInvoice($company, 10000);

    actingAsCompany($user, $company);

    $this->post("/portal/invoices/{$invoice->id}/credit-notes", [
        'issue_date' => now()->toDateString(),
        'amount' => '200.00',
    ])->assertSessionHasErrors('amount');

    expect(CreditNote::count())->toBe(0);
});

it('forbids staff from issuing credit notes', function () {
    [$staff, $company] = userWithCompany('staff');
    $invoice = creditInvoice($company);

    actingAsCompany($staff, $company);

    $this->post("/portal/invoices/{$invoice->id}/credit-notes", [
        'issue_date' => now()->toDateString(),
        'amount' => '10.00',
    ])->assertForbidden();
});

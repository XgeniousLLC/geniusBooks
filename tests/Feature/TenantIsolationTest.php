<?php

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Transaction;
use App\Models\Vendor;
use Tests\Concerns\AssertsTenantIsolation;

uses(AssertsTenantIsolation::class);

it('blocks cross-tenant member management', function () {
    [$ownerA, $companyA] = userWithCompany('owner');
    [$ownerB] = userWithCompany('owner');

    $this->assertCrossTenantDenied(
        $ownerA,
        $companyA,
        'PATCH',
        "/portal/settings/members/{$ownerB->id}/role",
        ['role' => 'staff'],
    );

    $this->assertCrossTenantDenied(
        $ownerA,
        $companyA,
        'DELETE',
        "/portal/settings/members/{$ownerB->id}",
    );
});

it('blocks cross-tenant invitation revocation', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $invitation = Invitation::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied(
        $ownerA,
        $companyA,
        'DELETE',
        "/portal/settings/invitations/{$invitation->id}",
    );
});

it('blocks cross-tenant customer access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $customerB = Customer::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/customers/{$customerB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'PATCH', "/portal/customers/{$customerB->id}", [
        'name' => 'Hijacked',
        'payment_terms_days' => 0,
        'is_active' => true,
    ]);
    $this->assertCrossTenantDenied($ownerA, $companyA, 'DELETE', "/portal/customers/{$customerB->id}");
});

it('blocks cross-tenant product access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $productB = Product::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'PATCH', "/portal/products/{$productB->id}", [
        'name' => 'Hijacked',
        'type' => 'service',
        'unit_price' => '1.00',
        'is_active' => true,
    ]);
    $this->assertCrossTenantDenied($ownerA, $companyA, 'DELETE', "/portal/products/{$productB->id}");
});

it('blocks cross-tenant invoice access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $invoiceB = Invoice::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/invoices/{$invoiceB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'DELETE', "/portal/invoices/{$invoiceB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/invoices/{$invoiceB->id}/cancel");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/invoices/{$invoiceB->id}/pdf");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/invoices/{$invoiceB->id}/email");
});

it('blocks cross-tenant account and payment access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $accountB = BankAccount::factory()->for($companyB)->create();
    $paymentB = Payment::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/accounts/{$accountB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/payments/{$paymentB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/payments/{$paymentB->id}/void", ['reason' => 'x']);
});

it('blocks cross-tenant expense and vendor access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $expenseB = Expense::factory()->for($companyB)->create();
    $vendorB = Vendor::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/expenses/{$expenseB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/expenses/{$expenseB->id}/void", ['reason' => 'x']);
    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/vendors/{$vendorB->id}/edit");
});

it('blocks cross-tenant ledger access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $ledgerB = LedgerAccount::factory()->for($companyB)->create();
    $transactionB = Transaction::factory()->for($companyB)->create(['type' => 'adjustment']);

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/chart-of-accounts/{$ledgerB->id}/edit");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/transactions/{$transactionB->id}/reverse", ['reason' => 'x']);
});

it('blocks cross-tenant quote access', function () {
    [$ownerA, $companyA] = userWithCompany('owner');

    $companyB = Company::factory()->create();
    $quoteB = Quote::factory()->for($companyB)->create();

    $this->assertCrossTenantDenied($ownerA, $companyA, 'GET', "/portal/quotes/{$quoteB->id}");
    $this->assertCrossTenantDenied($ownerA, $companyA, 'POST', "/portal/quotes/{$quoteB->id}/convert");
});

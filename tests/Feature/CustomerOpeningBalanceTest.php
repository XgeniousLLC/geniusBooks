<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;

it('records a customer opening balance as a ledger entry', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post("/portal/customers/{$customer->id}/opening-balance", [
        'date' => now()->toDateString(),
        'amount' => '250.00',
        'type' => 'debit',
    ])->assertRedirect();

    $transaction = Transaction::where('source_type', Customer::class)->where('source_id', $customer->id)->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->amount)->toBe(25000)
        ->and($transaction->direction)->toBe('in');

    $this->get("/portal/customers/{$customer->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('summary.opening', '$250.00'));
});

it('includes the opening balance in the customer statement', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();
    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 100000, 'subtotal' => 100000, 'issue_date' => now()->toDateString(),
    ]);

    actingAsCompany($user, $company);

    $this->post("/portal/customers/{$customer->id}/opening-balance", [
        'date' => now()->toDateString(),
        'amount' => '500.00',
        'type' => 'debit',
    ]);

    $this->get("/portal/reports/customers/{$customer->id}/statement")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Statement')
            ->where('statement.closing_display', '$1,500.00')
            ->has('statement.rows', 2));
});

it('forbids staff from setting opening balances', function () {
    [$staff, $company] = userWithCompany('staff');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($staff, $company);

    $this->post("/portal/customers/{$customer->id}/opening-balance", [
        'date' => now()->toDateString(),
        'amount' => '100.00',
        'type' => 'debit',
    ])->assertForbidden();
});

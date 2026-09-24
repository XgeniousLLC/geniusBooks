<?php

use App\Models\BankAccount;
use App\Models\LedgerAccount;
use App\Models\Transaction;

it('records a transfer between two accounts', function () {
    [$user, $company] = userWithCompany('owner');
    $from = BankAccount::factory()->for($company)->create(['opening_balance' => 50000]);
    $to = BankAccount::factory()->for($company)->create(['opening_balance' => 0]);

    actingAsCompany($user, $company);

    $this->post('/portal/transactions/transfer', [
        'from_bank_account_id' => $from->id,
        'to_bank_account_id' => $to->id,
        'amount' => '200.00',
        'date' => now()->toDateString(),
        'description' => 'Move to savings',
    ])->assertRedirect(route('portal.transactions.index'));

    expect($from->balance())->toBe(30000)
        ->and($to->balance())->toBe(20000)
        ->and(Transaction::where('type', 'transfer')->count())->toBe(2);
});

it('records a direct income against a revenue account', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 0]);
    $revenue = LedgerAccount::factory()->for($company)->revenue()->create(['code' => '4100', 'name' => 'Sales']);

    actingAsCompany($user, $company);

    $this->post('/portal/transactions/income', [
        'bank_account_id' => $account->id,
        'ledger_account_id' => $revenue->id,
        'amount' => '500.00',
        'date' => now()->toDateString(),
        'description' => 'Consulting income',
    ])->assertRedirect();

    $transaction = Transaction::where('type', 'income')->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->direction)->toBe('in')
        ->and($transaction->ledger_account_id)->toBe($revenue->id)
        ->and($account->balance())->toBe(50000);
});

it('records an adjustment and reverses it', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 10000]);

    actingAsCompany($user, $company);

    $this->post('/portal/transactions/adjustment', [
        'bank_account_id' => $account->id,
        'direction' => 'out',
        'amount' => '30.00',
        'date' => now()->toDateString(),
        'description' => 'Bank fee',
        'reason' => 'Fee correction',
    ])->assertRedirect();

    $transaction = Transaction::where('type', 'adjustment')->first();
    expect($account->balance())->toBe(7000);

    $this->post("/portal/transactions/{$transaction->id}/reverse", ['reason' => 'Entered twice'])->assertRedirect();

    expect($account->balance())->toBe(10000);
});

it('does not allow reversing system transactions', function () {
    [$user, $company] = userWithCompany('owner');
    $paymentTransaction = Transaction::factory()->for($company)->create(['type' => 'payment', 'direction' => 'in']);

    actingAsCompany($user, $company);

    $this->post("/portal/transactions/{$paymentTransaction->id}/reverse", ['reason' => 'x'])->assertForbidden();
});

it('forbids staff from the transaction ledger', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/transactions')->assertForbidden();
});

it('filters transactions by type', function () {
    [$user, $company] = userWithCompany('owner');
    Transaction::factory()->for($company)->create(['type' => 'income', 'direction' => 'in', 'description' => 'Income']);
    Transaction::factory()->for($company)->create(['type' => 'expense', 'direction' => 'out', 'description' => 'Expense']);

    actingAsCompany($user, $company);

    $this->get('/portal/transactions?type=income')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Transactions/Index')->has('transactions.data', 1));
});

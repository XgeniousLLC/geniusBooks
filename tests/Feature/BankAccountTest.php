<?php

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Services\Accounting\LedgerPostingService;

it('creates a bank account with a minor-unit opening balance', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/accounts', [
        'name' => 'Business Bank',
        'type' => 'bank',
        'opening_balance' => '250.00',
        'is_active' => true,
    ])->assertRedirect();

    $account = BankAccount::where('name', 'Business Bank')->first();

    expect($account)->not->toBeNull()
        ->and($account->opening_balance)->toBe(25000)
        ->and($account->currency)->toBe($company->currency);
});

it('shows a running balance in the account history', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 10000]);

    app(LedgerPostingService::class)->in($company, $account, 5000, TransactionType::Income->value, 'Income', now(), null);
    app(LedgerPostingService::class)->out($company, $account, 2000, TransactionType::Expense->value, 'Expense', now(), null);

    actingAsCompany($user, $company);

    $this->get("/portal/accounts/{$account->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Accounts/Show')
            ->where('account.balance_display', '$130.00')
            ->has('transactions', 2));
});

it('forbids staff from managing accounts', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/accounts')->assertForbidden();
});

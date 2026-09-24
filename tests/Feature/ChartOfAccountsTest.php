<?php

use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Services\CompanyProvisioningService;

it('seeds a default chart of accounts when a company is created', function () {
    $owner = \App\Models\User::factory()->create();

    $company = app(CompanyProvisioningService::class)->createFor($owner, [
        'name' => 'Acme', 'currency' => 'USD',
    ]);

    $accounts = LedgerAccount::withoutCompanyScope()->where('company_id', $company->id)->get();

    expect($accounts)->toHaveCount(count(LedgerAccount::DEFAULTS));

    $assets = $accounts->firstWhere('code', '1000');
    $cash = $accounts->firstWhere('code', '1100');

    expect($assets->type)->toBe('asset')
        ->and($cash->parent_id)->toBe($assets->id);
});

it('renders the chart of accounts', function () {
    [$user, $company] = userWithCompany('owner');
    LedgerAccount::factory()->for($company)->revenue()->create(['code' => '4100', 'name' => 'Sales']);

    actingAsCompany($user, $company);

    $this->get('/portal/chart-of-accounts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Accounts/Chart')->has('groups'));
});

it('forbids staff from the chart of accounts', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/chart-of-accounts')->assertForbidden();
});

it('creates a child account', function () {
    [$user, $company] = userWithCompany('owner');
    $parent = LedgerAccount::factory()->for($company)->create(['code' => '5000', 'name' => 'Expenses', 'type' => 'expense']);

    actingAsCompany($user, $company);

    $this->post('/portal/chart-of-accounts', [
        'code' => '5400',
        'name' => 'Travel',
        'type' => 'expense',
        'parent_id' => $parent->id,
        'is_active' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('ledger_accounts', ['company_id' => $company->id, 'code' => '5400', 'parent_id' => $parent->id]);
});

it('refuses to delete an account with children or transactions', function () {
    [$user, $company] = userWithCompany('owner');
    $parent = LedgerAccount::factory()->for($company)->create(['code' => '1000']);
    LedgerAccount::factory()->for($company)->create(['code' => '1100', 'parent_id' => $parent->id]);

    actingAsCompany($user, $company);

    $this->delete("/portal/chart-of-accounts/{$parent->id}")->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('ledger_accounts', ['id' => $parent->id, 'deleted_at' => null]);

    $withTransaction = LedgerAccount::factory()->for($company)->create(['code' => '9999']);
    Transaction::factory()->for($company)->create(['ledger_account_id' => $withTransaction->id]);

    $this->delete("/portal/chart-of-accounts/{$withTransaction->id}")->assertRedirect()->assertSessionHas('error');
});

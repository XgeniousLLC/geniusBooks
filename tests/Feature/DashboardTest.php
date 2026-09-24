<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;

it('shows the dashboard with KPIs for a member', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 100000, 'subtotal' => 100000, 'issue_date' => now()->toDateString(),
    ]);
    Expense::factory()->for($company)->create(['amount' => 30000, 'date' => now()->toDateString()]);

    actingAsCompany($user, $company);

    $this->get('/portal')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('kpis.0.value', '$1,000.00')
            ->where('kpis.1.value', '$300.00')
            ->where('kpis.4.value', '$700.00')
            ->has('revenueVsExpenses', 6)
            ->has('recentTransactions')
            ->has('outstandingInvoices'));
});

it('allows staff to view the dashboard', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal')->assertOk();
});

it('switches the dashboard period and trend', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->get('/portal?period=month&trend=weekly')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('selected.period', 'month')
            ->where('selected.trend', 'weekly'));
});

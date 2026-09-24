<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Reporting\ReportService;
use Illuminate\Support\Carbon;

function reportCompany(): array
{
    return userWithCompany('owner');
}

it('computes profit and loss from invoiced revenue and expenses', function () {
    [$user, $company] = reportCompany();
    $customer = Customer::factory()->for($company)->create();

    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 100000, 'subtotal' => 100000, 'tax_total' => 10000,
        'issue_date' => now()->toDateString(),
    ]);
    Expense::factory()->for($company)->create(['amount' => 30000, 'date' => now()->toDateString()]);

    actingAsCompany($user, $company);

    $report = app(ReportService::class)->profitAndLoss($company, Carbon::now()->startOfYear(), Carbon::now()->endOfDay());

    expect($report['summary'][0]['value'])->toBe(100000)
        ->and($report['summary'][1]['value'])->toBe(30000)
        ->and($report['summary'][2]['value'])->toBe(70000);
});

it('summarises tax collected and paid', function () {
    [$user, $company] = reportCompany();
    $customer = Customer::factory()->for($company)->create();

    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 110000, 'subtotal' => 100000, 'tax_total' => 10000, 'issue_date' => now()->toDateString(),
    ]);
    Expense::factory()->for($company)->create(['amount' => 30000, 'tax_amount' => 3000, 'date' => now()->toDateString()]);

    $report = app(ReportService::class)->taxSummary($company, Carbon::now()->startOfYear(), Carbon::now()->endOfDay());

    expect($report['summary'][0]['value'])->toBe(10000)
        ->and($report['summary'][1]['value'])->toBe(3000)
        ->and($report['summary'][2]['value'])->toBe(7000);
});

it('ages outstanding receivables', function () {
    [$user, $company] = reportCompany();
    $customer = Customer::factory()->for($company)->create();

    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 50000, 'subtotal' => 50000, 'amount_paid' => 0,
        'issue_date' => now()->subDays(40)->toDateString(),
        'due_date' => now()->subDays(35)->toDateString(),
    ]);

    $report = app(ReportService::class)->receivables($company, now());

    expect($report['summary'][0]['value'])->toBe(50000)
        ->and($report['summary'][1]['value'])->toBe(50000);
});

it('builds a customer statement with a closing balance', function () {
    [$user, $company] = reportCompany();
    $customer = Customer::factory()->for($company)->create();

    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 50000, 'subtotal' => 50000, 'issue_date' => now()->toDateString(),
    ]);
    Payment::factory()->for($company)->for($customer)->create(['amount' => 20000, 'date' => now()->toDateString()]);

    $statement = app(ReportService::class)->customerStatement($customer, Carbon::now()->startOfYear(), Carbon::now()->endOfDay());

    expect($statement['closing_display'])->toBe('$300.00')
        ->and($statement['rows'])->toHaveCount(2);
});

it('shows reports to an owner', function () {
    [$user, $company] = reportCompany();
    actingAsCompany($user, $company);

    $this->get('/portal/reports')->assertOk()->assertInertia(fn ($page) => $page->component('Reports/Index'));
    $this->get('/portal/reports/profit-and-loss')->assertOk()->assertInertia(fn ($page) => $page->component('Reports/View'));
});

it('forbids staff from reports', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/reports/profit-and-loss')->assertForbidden();
});

it('exports reports as csv and pdf', function () {
    [$user, $company] = reportCompany();
    actingAsCompany($user, $company);

    $this->get('/portal/reports/profit-and-loss?format=csv')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->get('/portal/reports/profit-and-loss?format=pdf')
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('renders every report route with data', function () {
    [$user, $company] = reportCompany();
    $customer = Customer::factory()->for($company)->create();
    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 50000, 'subtotal' => 50000, 'tax_total' => 5000, 'issue_date' => now()->toDateString(),
    ]);
    Expense::factory()->for($company)->create(['amount' => 20000, 'date' => now()->toDateString()]);
    Payment::factory()->for($company)->for($customer)->create(['amount' => 10000]);

    actingAsCompany($user, $company);

    foreach (['profit-and-loss', 'income', 'expenses', 'receivables', 'tax-summary'] as $report) {
        $this->get("/portal/reports/{$report}")->assertOk();
    }
});

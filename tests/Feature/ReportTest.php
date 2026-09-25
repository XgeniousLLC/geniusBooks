<?php

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Services\Accounting\LedgerPostingService;
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

it('builds a general ledger for a bank account', function () {
    [$user, $company] = reportCompany();
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 1000]);
    $ledger = app(LedgerPostingService::class);

    $ledger->in($company, $account, 2500, TransactionType::Income->value, 'Earlier gift', '2025-12-31');
    $ledger->in($company, $account, 4000, TransactionType::Income->value, 'January sale', '2026-01-10');
    $ledger->out($company, $account, 1500, TransactionType::Expense->value, 'January supplies', '2026-01-20');

    actingAsCompany($user, $company);

    $report = app(ReportService::class)->generalLedger($company, 'bank', $account->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($report['opening_display'])->toBe('$35.00')
        ->and($report['closing_display'])->toBe('$60.00')
        ->and($report['rows'])->toHaveCount(2)
        ->and($report['rows'][0]['balance_display'])->toBe('$75.00');
});

it('builds a general ledger for a chart account', function () {
    [$user, $company] = reportCompany();
    $ledgerAccount = LedgerAccount::factory()->for($company)->revenue()->create(['name' => 'Sales revenue']);

    app(LedgerPostingService::class)->post($company, [
        'ledger_account_id' => $ledgerAccount->id,
        'type' => TransactionType::Income->value,
        'direction' => 'in',
        'amount' => 800,
        'currency' => 'USD',
        'description' => 'December revenue',
        'occurred_on' => '2025-12-20',
    ]);
    app(LedgerPostingService::class)->post($company, [
        'ledger_account_id' => $ledgerAccount->id,
        'type' => TransactionType::Income->value,
        'direction' => 'in',
        'amount' => 1200,
        'currency' => 'USD',
        'description' => 'January revenue',
        'occurred_on' => '2026-01-15',
    ]);

    actingAsCompany($user, $company);

    $report = app(ReportService::class)->generalLedger($company, 'ledger', $ledgerAccount->id, Carbon::parse('2026-01-01'), Carbon::parse('2026-01-31'));

    expect($report['opening_display'])->toBe('$8.00')
        ->and($report['closing_display'])->toBe('$20.00')
        ->and($report['rows'])->toHaveCount(1);
});

it('renders the general ledger page and exports its rows', function () {
    [$user, $company] = reportCompany();
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 0]);

    app(LedgerPostingService::class)->in($company, $account, 5000, TransactionType::Income->value, 'Client receipt', now()->toDateString());

    actingAsCompany($user, $company);

    $this->get("/portal/reports/general-ledger?account_type=bank&account_id={$account->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/GeneralLedger')
            ->where('ledger.closing_display', '$50.00'));

    $from = now()->startOfYear()->toDateString();
    $to = now()->toDateString();
    $response = $this->get("/portal/reports/general-ledger?account_type=bank&account_id={$account->id}&from={$from}&to={$to}&format=csv");

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

    expect($response->streamedContent())
        ->toContain('Opening balance')
        ->toContain('Closing balance')
        ->toContain('Client receipt');
});

it('does not leak another company’s account into the general ledger', function () {
    [$user, $company] = userWithCompany('owner');
    $otherCompany = \App\Models\Company::factory()->create();
    $otherAccount = BankAccount::factory()->for($otherCompany)->create(['opening_balance' => 999900]);

    actingAsCompany($user, $company);

    $this->get("/portal/reports/general-ledger?account_type=bank&account_id={$otherAccount->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/GeneralLedger')
            ->where('ledger.account_name', 'Unknown account')
            ->where('ledger.rows', []));
});

it('validates general ledger filters and export options', function () {
    [$user, $company] = reportCompany();
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->get("/portal/reports/general-ledger?account_type=nope&account_id={$account->id}&from=not-a-date")
        ->assertRedirect()
        ->assertSessionHasErrors(['account_type', 'from']);
});

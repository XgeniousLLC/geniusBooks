<?php

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Reporting\ReportService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Carbon;

it('seeds the Section 31 demo journey', function () {
    $this->seed(DemoDataSeeder::class);

    $company = Company::where('name', 'ABC Digital Agency')->first();
    expect($company)->not->toBeNull();

    $invoice = Invoice::withoutCompanyScope()->where('company_id', $company->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->total)->toBe(250000)
        ->and($invoice->amount_paid)->toBe(150000);

    $report = app(ReportService::class)->profitAndLoss($company, Carbon::now()->startOfYear(), Carbon::now()->endOfDay());

    expect($report['summary'][0]['value'])->toBe(250000)
        ->and($report['summary'][1]['value'])->toBe(30000)
        ->and($report['summary'][2]['value'])->toBe(220000);
});

it('exposes working demo credentials', function () {
    $this->seed(DemoDataSeeder::class);

    expect(\Illuminate\Support\Facades\Auth::guard('web')->attempt([
        'email' => config('accounting.demo.email'),
        'password' => config('accounting.demo.password'),
    ]))->toBeTrue();
});

it('seeds a richly populated second company to explore', function () {
    $this->seed(DemoDataSeeder::class);

    $nova = Company::where('name', 'Nova Retail Ltd')->first();

    expect($nova)->not->toBeNull()
        ->and(BankAccount::withoutCompanyScope()->where('company_id', $nova->id)->count())->toBe(3)
        ->and(Invoice::withoutCompanyScope()->where('company_id', $nova->id)->count())->toBeGreaterThanOrEqual(15)
        ->and(Payment::withoutCompanyScope()->where('company_id', $nova->id)->count())->toBeGreaterThanOrEqual(6)
        ->and(Expense::withoutCompanyScope()->where('company_id', $nova->id)->count())->toBeGreaterThanOrEqual(5);
});

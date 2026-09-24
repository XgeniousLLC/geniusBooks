<?php

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Services\DocumentNumberService;
use Illuminate\Database\QueryException;

it('issues sequential zero-padded numbers per company', function () {
    $company = Company::factory()->create([
        'invoice_prefix' => 'INV-',
        'invoice_number_padding' => 4,
    ]);

    $service = app(DocumentNumberService::class);

    expect($service->preview($company, 'invoice'))->toBe('INV-0001')
        ->and($service->next($company, 'invoice'))->toBe('INV-0001')
        ->and($service->next($company, 'invoice'))->toBe('INV-0002')
        ->and($service->next($company, 'invoice'))->toBe('INV-0003')
        ->and($service->preview($company, 'invoice'))->toBe('INV-0004');
});

it('keeps sequences independent between companies', function () {
    $a = Company::factory()->create(['invoice_prefix' => 'A-']);
    $b = Company::factory()->create(['invoice_prefix' => 'B-']);

    $service = app(DocumentNumberService::class);

    expect($service->next($a, 'invoice'))->toBe('A-0001')
        ->and($service->next($b, 'invoice'))->toBe('B-0001')
        ->and($service->next($a, 'invoice'))->toBe('A-0002');
});

it('enforces one sequence row per company and type', function () {
    $company = Company::factory()->create();

    DocumentSequence::withoutCompanyScope()->create([
        'company_id' => $company->id, 'type' => 'invoice', 'prefix' => 'INV-', 'padding' => 4,
    ]);

    DocumentSequence::withoutCompanyScope()->create([
        'company_id' => $company->id, 'type' => 'invoice', 'prefix' => 'DUP-', 'padding' => 4,
    ]);
})->throws(QueryException::class);

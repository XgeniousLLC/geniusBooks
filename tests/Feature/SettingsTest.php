<?php

use App\Models\Invoice;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('updates business settings', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->patch('/portal/settings/business', [
        'name' => 'Renamed Co',
        'email' => 'hi@renamed.test',
        'phone' => '123',
        'address' => '1 St',
        'country' => 'us',
        'currency' => 'USD',
        'currency_position' => 'prefix',
        'timezone' => 'UTC',
        'financial_year_start_month' => 4,
        'financial_year_start_day' => 1,
    ])->assertRedirect();

    $company->refresh();

    expect($company->name)->toBe('Renamed Co')
        ->and($company->country)->toBe('US')
        ->and($company->financial_year_start_month)->toBe(4);
});

it('allows changing the currency and its display format', function () {
    [$user, $company] = userWithCompany('owner', ['currency' => 'USD']);
    Invoice::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->patch('/portal/settings/business', [
        'name' => $company->name,
        'currency' => 'EUR',
        'currency_symbol' => '€',
        'currency_position' => 'suffix',
        'timezone' => 'UTC',
        'financial_year_start_month' => 1,
        'financial_year_start_day' => 1,
    ])->assertRedirect();

    $company->refresh();

    expect($company->currency)->toBe('EUR')
        ->and($company->currency_symbol)->toBe('€')
        ->and($company->currency_position)->toBe('suffix');
});

it('uploads a business logo', function () {
    Storage::fake('local');

    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/settings/business', [
        '_method' => 'patch',
        'name' => $company->name,
        'currency' => 'USD',
        'currency_position' => 'prefix',
        'timezone' => 'UTC',
        'financial_year_start_month' => 1,
        'financial_year_start_day' => 1,
        'logo' => UploadedFile::fake()->image('logo.png'),
    ])->assertRedirect();

    expect($company->fresh()->logo_path)->not->toBeNull();
});

it('updates invoice and tax settings', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->patch('/portal/settings/invoices', [
        'invoice_prefix' => 'ACME-',
        'invoice_number_padding' => 5,
        'default_payment_terms_days' => 30,
        'default_invoice_terms' => 'Net 30',
        'invoice_footer' => 'Thanks',
    ])->assertRedirect();

    $this->patch('/portal/settings/tax', [
        'tax_registration_number' => 'TAX-1',
        'default_tax_rate' => 12.5,
        'tax_inclusive' => true,
    ])->assertRedirect();

    $company->refresh();

    expect($company->invoice_prefix)->toBe('ACME-')
        ->and($company->default_payment_terms_days)->toBe(30)
        ->and($company->tax_registration_number)->toBe('TAX-1')
        ->and((float) $company->default_tax_rate)->toBe(12.5)
        ->and($company->tax_inclusive)->toBeTrue();
});

it('forbids staff from settings', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/settings/business')->assertForbidden();
    $this->get('/portal/settings/invoices')->assertForbidden();
    $this->get('/portal/settings/tax')->assertForbidden();
});

it('exports tenant data as a download', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->get('/portal/settings/export')
        ->assertOk()
        ->assertDownload();
});

<?php

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioningService;

it('redirects company-less users to onboarding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/portal')
        ->assertRedirect(route('portal.onboarding'));
});

it('renders the onboarding wizard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/portal/onboarding')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Onboarding/Wizard'));
});

it('creates a company, membership and owner role on submit', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/portal/onboarding', [
        'name' => 'ABC Digital Agency',
        'email' => 'hello@abc.test',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'financial_year_start_month' => 1,
        'financial_year_start_day' => 1,
        'tax_inclusive' => false,
        'default_tax_rate' => 10,
        'invoice_prefix' => 'INV-',
        'invoice_number_padding' => 4,
        'default_payment_terms_days' => 15,
    ]);

    $company = Company::firstWhere('name', 'ABC Digital Agency');

    $response->assertRedirect(route('portal.home'));

    expect($company)->not->toBeNull()
        ->and($company->onboarded_at)->not->toBeNull()
        ->and($company->currency)->toBe('USD')
        ->and($user->fresh()->belongsToCompany($company->id))->toBeTrue();

    $this->assertDatabaseHas('company_user', [
        'company_id' => $company->id,
        'user_id' => $user->id,
        'is_active' => true,
    ]);
});

it('validates onboarding input', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/portal/onboarding', ['name' => '', 'currency' => 'ZZZ'])
        ->assertSessionHasErrors(['name', 'currency', 'timezone', 'default_tax_rate', 'invoice_prefix']);
});

it('redirects users who already have a company away from onboarding', function () {
    $user = User::factory()->create();
    $company = app(CompanyProvisioningService::class)->createFor($user, [
        'name' => 'Existing Co', 'currency' => 'USD', 'onboarded_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/portal/onboarding')
        ->assertRedirect(route('portal.home'));
});

it('blocks unverified users from onboarding', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get('/portal/onboarding')
        ->assertRedirect(route('verification.notice'));
});

it('lets an existing user add another business', function () {
    [$owner, $company] = userWithCompany('owner');
    actingAsCompany($owner, $company);

    $this->get('/portal/businesses/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Onboarding/Wizard')->where('adding', true));

    $this->post('/portal/businesses/create', [
        'name' => 'Second Ventures',
        'currency' => 'EUR',
        'timezone' => 'Europe/London',
        'financial_year_start_month' => 1,
        'financial_year_start_day' => 1,
        'tax_inclusive' => false,
        'default_tax_rate' => 20,
        'invoice_prefix' => 'SV-',
        'invoice_number_padding' => 4,
        'default_payment_terms_days' => 30,
    ])->assertRedirect(route('portal.home'));

    $second = Company::firstWhere('name', 'Second Ventures');

    expect($second)->not->toBeNull()
        ->and($owner->activeCompanies()->count())->toBe(2)
        ->and($owner->belongsToCompany($second->id))->toBeTrue();

    $this->assertDatabaseHas('company_user', ['company_id' => $second->id, 'user_id' => $owner->id]);
});

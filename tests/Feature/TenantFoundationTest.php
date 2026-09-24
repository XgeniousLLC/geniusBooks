<?php

use App\Models\Company;
use App\Models\DocumentSequence;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Support\CompanyContext;
use Spatie\Permission\Models\Role;

it('provisions a company with an owner membership and role', function () {
    $user = User::factory()->create();

    $company = app(CompanyProvisioningService::class)->createFor($user, [
        'name' => 'ABC Digital Agency',
        'currency' => 'USD',
        'invoice_prefix' => 'INV-',
    ]);

    expect($company->slug)->not->toBeEmpty()
        ->and($user->fresh()->belongsToCompany($company->id))->toBeTrue();

    setPermissionsTeamId($company->id);

    expect($user->fresh()->hasRole('owner'))->toBeTrue()
        ->and(Role::where('team_id', $company->id)->pluck('name')->all())
        ->toContain('owner', 'accountant', 'staff');

    setPermissionsTeamId(null);
});

it('scopes reads to the active company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    DocumentSequence::withoutCompanyScope()->create([
        'company_id' => $a->id, 'type' => 'invoice', 'prefix' => 'A-', 'padding' => 4,
    ]);
    DocumentSequence::withoutCompanyScope()->create([
        'company_id' => $b->id, 'type' => 'invoice', 'prefix' => 'B-', 'padding' => 4,
    ]);

    app(CompanyContext::class)->set($a->id);

    expect(DocumentSequence::count())->toBe(1)
        ->and(DocumentSequence::first()->company_id)->toBe($a->id);
});

it('stamps company_id from the active context on create', function () {
    $company = Company::factory()->create();

    app(CompanyContext::class)->set($company->id);

    $sequence = DocumentSequence::create([
        'type' => 'invoice', 'prefix' => 'INV-', 'padding' => 4,
    ]);

    expect($sequence->company_id)->toBe($company->id);
});

it('refuses to persist a record for another company', function () {
    $a = Company::factory()->create();
    $b = Company::factory()->create();

    app(CompanyContext::class)->set($a->id);

    DocumentSequence::withoutCompanyScope()->create([
        'company_id' => $b->id, 'type' => 'invoice', 'prefix' => 'B-', 'padding' => 4,
    ]);
})->throws(RuntimeException::class);

<?php

use App\Models\User;
use App\Services\CompanyProvisioningService;

it('shows the users page to an owner', function () {
    [$owner, $company] = userWithCompany('owner');

    actingAsCompany($owner, $company);

    $this->get('/portal/settings/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings/Users'));
});

it('forbids staff from managing users', function () {
    [$staff, $company] = userWithCompany('staff');

    actingAsCompany($staff, $company);

    $this->get('/portal/settings/users')->assertForbidden();
});

it('lets an owner change a member role', function () {
    [$owner, $company] = userWithCompany('owner');
    $member = User::factory()->create();
    $company->users()->attach($member->id, ['is_active' => true]);
    app(CompanyProvisioningService::class)->assignRole($company, $member, 'staff');

    actingAsCompany($owner, $company);

    $this->patch("/portal/settings/members/{$member->id}/role", ['role' => 'accountant'])
        ->assertRedirect();

    expect(app(CompanyProvisioningService::class)->roleOf($company, $member->fresh())->value)
        ->toBe('accountant');
});

it('prevents demoting the last owner', function () {
    [$owner, $company] = userWithCompany('owner');

    actingAsCompany($owner, $company);

    $this->patch("/portal/settings/members/{$owner->id}/role", ['role' => 'staff'])
        ->assertSessionHas('error');

    expect(app(CompanyProvisioningService::class)->roleOf($company, $owner->fresh())->value)
        ->toBe('owner');
});

it('prevents removing the last owner', function () {
    [$owner, $company] = userWithCompany('owner');

    actingAsCompany($owner, $company);

    $this->delete("/portal/settings/members/{$owner->id}")
        ->assertSessionHas('error');
});

it('prevents removing yourself', function () {
    [$owner, $company] = userWithCompany('owner');
    $other = User::factory()->create();
    $company->users()->attach($other->id, ['is_active' => true]);
    app(CompanyProvisioningService::class)->assignRole($company, $other, 'owner');

    actingAsCompany($owner, $company);

    // Two owners exist, so the guard that fires is "cannot remove yourself".
    $this->delete("/portal/settings/members/{$owner->id}")
        ->assertSessionHas('error');
});

it('deactivates and reactivates a member', function () {
    [$owner, $company] = userWithCompany('owner');
    $member = User::factory()->create();
    $company->users()->attach($member->id, ['is_active' => true]);
    app(CompanyProvisioningService::class)->assignRole($company, $member, 'staff');

    actingAsCompany($owner, $company);

    $this->patch("/portal/settings/members/{$member->id}/deactivate")->assertRedirect();
    expect($company->users()->whereKey($member->id)->first()->pivot->is_active)->toBeFalsy();

    $this->patch("/portal/settings/members/{$member->id}/reactivate")->assertRedirect();
    expect($company->users()->whereKey($member->id)->first()->pivot->is_active)->toBeTruthy();
});

it('removes a member and their roles', function () {
    [$owner, $company] = userWithCompany('owner');
    $member = User::factory()->create();
    $company->users()->attach($member->id, ['is_active' => true]);
    $provisioning = app(CompanyProvisioningService::class);
    $provisioning->assignRole($company, $member, 'staff');

    actingAsCompany($owner, $company);

    $this->delete("/portal/settings/members/{$member->id}")->assertRedirect();

    expect($company->users()->whereKey($member->id)->exists())->toBeFalse()
        ->and($provisioning->roleOf($company, $member->fresh()))->toBeNull();
});

<?php

use App\Enums\Permission;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyProvisioningService;
use App\Support\CompanyContext;

function companyWithTeam(): array
{
    $company = Company::factory()->create();
    $provisioning = app(CompanyProvisioningService::class);
    $provisioning->ensureRoles($company);

    $users = [];

    foreach (['owner', 'accountant', 'staff'] as $role) {
        $user = User::factory()->create();
        $company->users()->attach($user->id, ['is_active' => true]);
        $provisioning->assignRole($company, $user, $role);
        $users[$role] = $user;
    }

    return [$company, $users];
}

it('grants and denies abilities according to role', function () {
    [$company, $users] = companyWithTeam();

    app(CompanyContext::class)->set($company->id);

    expect($users['owner']->can(Permission::ManageUsers))->toBeTrue()
        ->and($users['owner']->can(Permission::ManageCompany))->toBeTrue()
        ->and($users['accountant']->can(Permission::ManageUsers))->toBeFalse()
        ->and($users['accountant']->can(Permission::ViewReports))->toBeTrue()
        ->and($users['accountant']->can(Permission::ManageFinances))->toBeTrue()
        ->and($users['staff']->can(Permission::ManageSales))->toBeTrue()
        ->and($users['staff']->can(Permission::ManageFinances))->toBeFalse();
});

it('denies abilities when no company context is active', function () {
    [, $users] = companyWithTeam();

    app(CompanyContext::class)->forget();

    expect($users['owner']->can(Permission::ManageUsers))->toBeFalse();
});

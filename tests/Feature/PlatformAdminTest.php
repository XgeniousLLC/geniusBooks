<?php

use App\Models\Admin;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;

it('blocks guests from the platform console', function () {
    $this->get(route('admin.companies.index'))->assertRedirect(route('admin.login'));
});

it('lists businesses for a platform admin', function () {
    Company::factory()->count(3)->create();
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get(route('admin.companies.index'))
        ->assertOk()
        ->assertSee('Businesses');
});

it('suspends and reactivates a business', function () {
    $admin = Admin::factory()->create();
    $company = Company::factory()->create();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.companies.suspend', $company))
        ->assertRedirect();

    expect($company->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin, 'admin')
        ->post(route('admin.companies.reactivate', $company))
        ->assertRedirect();

    expect($company->fresh()->is_active)->toBeTrue();
});

it('redirects members of a suspended business to the suspended page', function () {
    [$owner, $company] = userWithCompany('owner', ['is_active' => false]);

    actingAsCompany($owner, $company);

    $this->get('/portal')->assertRedirect(route('portal.suspended'));
});

it('lets a platform admin impersonate a business owner', function () {
    $admin = Admin::factory()->create();
    [$owner, $company] = userWithCompany('owner');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.companies.impersonate', $company))
        ->assertRedirect(route('portal.home'));

    $this->assertAuthenticatedAs($owner, 'web');
    expect(session('impersonator_admin_id'))->toBe($admin->id);
    expect(session('current_company_id'))->toBe($company->id);
});

it('stops impersonation and returns to the console', function () {
    $admin = Admin::factory()->create();
    [$owner, $company] = userWithCompany('owner');

    $this->actingAs($admin, 'admin')
        ->post(route('admin.companies.impersonate', $company));

    $this->assertAuthenticatedAs($owner, 'web');

    $this->post(route('portal.impersonate.stop'))
        ->assertRedirect(route('admin.companies.index'));

    expect(Auth::guard('web')->check())->toBeFalse();
    expect(session('impersonator_admin_id'))->toBeNull();
});

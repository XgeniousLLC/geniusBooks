<?php

use App\Models\Expense;
use App\Models\Vendor;

it('creates and lists vendors', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/vendors', [
        'name' => 'AWS',
        'email' => 'billing@aws.test',
        'is_active' => true,
    ])->assertRedirect(route('portal.vendors.index'));

    $this->assertDatabaseHas('vendors', ['company_id' => $company->id, 'name' => 'AWS']);

    $this->get('/portal/vendors?search=AWS')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Vendors/Index')->has('vendors.data', 1));
});

it('updates a vendor', function () {
    [$user, $company] = userWithCompany('owner');
    $vendor = Vendor::factory()->for($company)->create(['name' => 'Old']);

    actingAsCompany($user, $company);

    $this->patch("/portal/vendors/{$vendor->id}", ['name' => 'New', 'is_active' => true])->assertRedirect();

    expect($vendor->fresh()->name)->toBe('New');
});

it('refuses to delete a vendor that has expenses', function () {
    [$user, $company] = userWithCompany('owner');
    $vendor = Vendor::factory()->for($company)->create();
    Expense::factory()->for($company)->create(['vendor_id' => $vendor->id]);

    actingAsCompany($user, $company);

    $this->delete("/portal/vendors/{$vendor->id}")->assertRedirect()->assertSessionHas('error');
    $this->assertDatabaseHas('vendors', ['id' => $vendor->id, 'deleted_at' => null]);
});

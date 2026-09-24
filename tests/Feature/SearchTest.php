<?php

use App\Models\Customer;
use App\Models\Invoice;

it('finds records across modules', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create(['name' => 'Acme Corporation']);
    Invoice::factory()->for($company)->for($customer)->sent()->create(['number' => 'INV-ACME-1']);

    actingAsCompany($user, $company);

    $this->get('/portal/search?q=Acme')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Search/Index')
            ->where('groups', function ($groups) {
                $titles = collect($groups)->pluck('title')->all();

                return in_array('Customers', $titles, true) && in_array('Invoices', $titles, true);
            }));
});

it('ignores very short queries', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->get('/portal/search?q=a')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('groups', []));
});

it('limits results by role', function () {
    [$staff, $company] = userWithCompany('staff');
    actingAsCompany($staff, $company);

    $this->get('/portal/search?q=software')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('groups', function ($groups) {
            $titles = collect($groups)->pluck('title')->all();

            return ! in_array('Transactions', $titles, true);
        }));
});

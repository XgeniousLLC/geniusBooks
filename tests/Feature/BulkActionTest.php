<?php

use App\Models\Customer;
use App\Models\Product;

it('archives selected customers in bulk', function () {
    [$user, $company] = userWithCompany('owner');
    $customers = Customer::factory()->for($company)->count(3)->create(['is_active' => true]);

    actingAsCompany($user, $company);

    $this->post('/portal/customers/bulk', [
        'ids' => $customers->pluck('id')->all(),
        'action' => 'archive',
    ])->assertRedirect();

    expect(Customer::where('is_active', true)->count())->toBe(0)
        ->and(Customer::where('is_active', false)->count())->toBe(3);
});

it('bulk activates products', function () {
    [$user, $company] = userWithCompany('owner');
    $products = Product::factory()->for($company)->count(2)->create(['is_active' => false]);

    actingAsCompany($user, $company);

    $this->post('/portal/products/bulk', [
        'ids' => $products->pluck('id')->all(),
        'action' => 'activate',
    ])->assertRedirect();

    expect(Product::where('is_active', true)->count())->toBe(2);
});

it('validates bulk actions', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/customers/bulk', ['ids' => [], 'action' => 'delete'])
        ->assertSessionHasErrors(['ids', 'action']);
});

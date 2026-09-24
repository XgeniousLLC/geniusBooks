<?php

use App\Models\Customer;
use App\Models\Invoice;

it('shows the customers index', function () {
    [$user, $company] = userWithCompany('owner');
    Customer::factory()->for($company)->create(['name' => 'Acme Ltd']);

    actingAsCompany($user, $company);

    $this->get('/portal/customers')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Index')
            ->where('customers.data.0.name', 'Acme Ltd'));
});

it('creates a customer and locks currency to the company', function () {
    [$user, $company] = userWithCompany('owner', ['currency' => 'USD']);

    actingAsCompany($user, $company);

    $this->post('/portal/customers', [
        'name' => 'Northwind Traders',
        'company_name' => 'Northwind',
        'email' => 'billing@northwind.test',
        'payment_terms_days' => 30,
        'is_active' => true,
    ])->assertRedirect();

    $customer = Customer::where('name', 'Northwind Traders')->first();

    expect($customer)->not->toBeNull()
        ->and($customer->company_id)->toBe($company->id)
        ->and($customer->currency)->toBe('USD')
        ->and($customer->payment_terms_days)->toBe(30);
});

it('validates required customer fields', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/customers', ['name' => '', 'email' => 'not-an-email'])
        ->assertSessionHasErrors(['name', 'email']);
});

it('updates a customer', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create(['name' => 'Old Name']);

    actingAsCompany($user, $company);

    $this->patch("/portal/customers/{$customer->id}", [
        'name' => 'New Name',
        'payment_terms_days' => 7,
        'is_active' => true,
    ])->assertRedirect(route('portal.customers.show', $customer));

    expect($customer->fresh()->name)->toBe('New Name');
});

it('archives a customer with a soft delete', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->delete("/portal/customers/{$customer->id}")->assertRedirect(route('portal.customers.index'));

    $this->assertSoftDeleted('customers', ['id' => $customer->id]);
});

it('shows a customer financial summary', function () {
    [$user, $company] = userWithCompany('owner');
    $customer = Customer::factory()->for($company)->create();
    Invoice::factory()->for($company)->for($customer)->sent()->create([
        'total' => 10000,
        'subtotal' => 10000,
        'amount_paid' => 4000,
    ]);

    actingAsCompany($user, $company);

    $this->get("/portal/customers/{$customer->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Customers/Show')
            ->where('summary.outstanding', '$60.00')
            ->has('invoices', 1));
});

it('filters customers by search and status', function () {
    [$user, $company] = userWithCompany('owner');
    Customer::factory()->for($company)->create(['name' => 'Alpha Corp', 'is_active' => true]);
    Customer::factory()->for($company)->create(['name' => 'Beta Corp', 'is_active' => false]);

    actingAsCompany($user, $company);

    $this->get('/portal/customers?search=Alpha')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('customers.data', fn ($data) => count($data) === 1));

    $this->get('/portal/customers?status=inactive')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.data.0.name', 'Beta Corp'));
});

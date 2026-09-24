<?php

use App\Models\Product;

it('shows the products index', function () {
    [$user, $company] = userWithCompany('owner');
    Product::factory()->for($company)->service()->create(['name' => 'Business Consulting']);

    actingAsCompany($user, $company);

    $this->get('/portal/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Products/Index')
            ->where('products.data.0.name', 'Business Consulting'));
});

it('stores unit price in minor units', function () {
    [$user, $company] = userWithCompany('owner', ['currency' => 'USD']);

    actingAsCompany($user, $company);

    $this->post('/portal/products', [
        'name' => 'Website Development',
        'type' => 'service',
        'unit_price' => '2500.00',
        'tax_rate' => 10,
        'is_active' => true,
    ])->assertRedirect(route('portal.products.index'));

    $product = Product::where('name', 'Website Development')->first();

    expect($product)->not->toBeNull()
        ->and($product->unit_price)->toBe(250000)
        ->and($product->company_id)->toBe($company->id);
});

it('validates product type and price', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/products', [
        'name' => 'Bad',
        'type' => 'widget',
        'unit_price' => -5,
    ])->assertSessionHasErrors(['type', 'unit_price']);
});

it('enforces sku uniqueness within a company but not across companies', function () {
    [$userA, $companyA] = userWithCompany('owner');
    $companyB = \App\Models\Company::factory()->create();

    Product::factory()->for($companyA)->create(['sku' => 'SAME-1']);
    Product::factory()->for($companyB)->create(['sku' => 'SAME-1']);

    actingAsCompany($userA, $companyA);

    $this->post('/portal/products', [
        'name' => 'Duplicate SKU',
        'type' => 'product',
        'unit_price' => '10',
        'sku' => 'SAME-1',
    ])->assertSessionHasErrors('sku');
});

it('updates a product price', function () {
    [$user, $company] = userWithCompany('owner');
    $product = Product::factory()->for($company)->create(['unit_price' => 1000]);

    actingAsCompany($user, $company);

    $this->patch("/portal/products/{$product->id}", [
        'name' => $product->name,
        'type' => $product->type,
        'unit_price' => '19.99',
        'is_active' => true,
    ])->assertRedirect(route('portal.products.index'));

    expect($product->fresh()->unit_price)->toBe(1999);
});

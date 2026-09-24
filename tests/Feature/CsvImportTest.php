<?php

use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\UploadedFile;

function csv(string $name, array $rows): UploadedFile
{
    $content = implode("\n", array_map(fn ($row) => implode(',', $row), $rows));

    return UploadedFile::fake()->createWithContent($name, $content);
}

it('imports customers from csv', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $file = csv('customers.csv', [
        ['name', 'email', 'company_name', 'payment_terms_days'],
        ['Acme Ltd', 'billing@acme.test', 'Acme', '30'],
        ['Globex', 'ap@globex.test', 'Globex', '15'],
    ]);

    $this->post('/portal/customers/import', ['file' => $file])
        ->assertRedirect(route('portal.imports.customers'))
        ->assertSessionHas('importResult', fn ($result) => $result['imported'] === 2 && $result['errors'] === []);

    expect(Customer::where('company_id', $company->id)->count())->toBe(2);
});

it('aborts the whole import when a row is invalid', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $file = csv('customers.csv', [
        ['name', 'email'],
        ['Valid Person', 'valid@example.test'],
        ['', 'broken@example.test'],
    ]);

    $this->post('/portal/customers/import', ['file' => $file])
        ->assertSessionHas('importResult', fn ($result) => $result['imported'] === 0 && count($result['errors']) === 1);

    expect(Customer::where('company_id', $company->id)->count())->toBe(0);
});

it('skips duplicate customers', function () {
    [$user, $company] = userWithCompany('owner');
    Customer::factory()->for($company)->create(['email' => 'exists@example.test']);

    actingAsCompany($user, $company);

    $file = csv('customers.csv', [
        ['name', 'email'],
        ['Existing', 'exists@example.test'],
        ['New Person', 'new@example.test'],
    ]);

    $this->post('/portal/customers/import', ['file' => $file])
        ->assertSessionHas('importResult', fn ($result) => $result['imported'] === 1 && $result['skipped'] === 1);
});

it('rejects a csv without a name column', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $file = csv('bad.csv', [
        ['email', 'phone'],
        ['a@example.test', '123'],
    ]);

    $this->post('/portal/customers/import', ['file' => $file])
        ->assertSessionHasErrors('file');
});

it('imports products and converts prices to minor units', function () {
    [$user, $company] = userWithCompany('owner', ['currency' => 'USD']);
    actingAsCompany($user, $company);

    $file = csv('products.csv', [
        ['name', 'type', 'unit_price', 'tax_rate', 'sku'],
        ['Website Development', 'service', '2500.00', '10', 'WEB-DEV'],
        ['Hosting', 'service', '100', '0', 'HOST'],
    ]);

    $this->post('/portal/products/import', ['file' => $file])
        ->assertSessionHas('importResult', fn ($result) => $result['imported'] === 2);

    expect(Product::where('name', 'Website Development')->first()->unit_price)->toBe(250000);
});

it('downloads a csv template', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->get('/portal/customers/import/template')
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

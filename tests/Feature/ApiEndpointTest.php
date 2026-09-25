<?php

use App\Models\ApiApplication;
use App\Models\Customer;
use App\Models\Product;
use App\Models\BankAccount;
use App\Models\ExpenseCategory;

function apiTokenForCompany(\App\Models\Company $company, \App\Models\User $user): string
{
    $plain = ApiApplication::generateToken();
    ApiApplication::create([
        'company_id' => $company->id,
        'created_by' => $user->id,
        'name' => 'Test',
        'token_hash' => ApiApplication::hashToken($plain),
    ]);
    return $plain;
}

it('can CRUD customers via API', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);

    $headers = ['Authorization' => "Bearer {$token}"];

    // create
    $res = $this->postJson('/api/v1/customers', ['name' => 'Acme Corp', 'email' => 'acme@example.com'], $headers);
    $res->assertCreated()->assertJsonPath('name', 'Acme Corp');
    $id = $res->json('id');

    // index
    $this->getJson('/api/v1/customers', $headers)->assertOk()->assertJsonFragment(['name' => 'Acme Corp']);

    // show
    $this->getJson("/api/v1/customers/{$id}", $headers)->assertOk()->assertJsonPath('email', 'acme@example.com');

    // update
    $this->putJson("/api/v1/customers/{$id}", ['name' => 'Acme Updated'], $headers)->assertOk()->assertJsonPath('name', 'Acme Updated');

    // delete
    $this->deleteJson("/api/v1/customers/{$id}", [], $headers)->assertNoContent();
    $this->getJson("/api/v1/customers/{$id}", $headers)->assertNotFound();
});

it('can CRUD products via API', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);
    $headers = ['Authorization' => "Bearer {$token}"];

    $res = $this->postJson('/api/v1/products', ['name' => 'Widget', 'type' => 'product', 'unit_price' => 1000], $headers);
    $res->assertCreated();
    $id = $res->json('id');
    $this->getJson("/api/v1/products/{$id}", $headers)->assertOk();
});

it('can create invoice via API with calculator', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);
    $headers = ['Authorization' => "Bearer {$token}"];

    // need customer
    $customer = $this->postJson('/api/v1/customers', ['name' => 'Billable Co'], $headers)->json();

    $res = $this->postJson('/api/v1/invoices', [
        'customer_id' => $customer['id'],
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'items' => [
            ['description' => 'Service', 'quantity' => 2, 'unit_price' => '50.00'],
        ],
    ], $headers);

    $res->assertCreated()
        ->assertJsonPath('customer_id', $customer['id'])
        ->assertJsonPath('total', 10000); // 2 * 50.00 in cents
});

it('validates invoice payload', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);
    $headers = ['Authorization' => "Bearer {$token}"];

    $this->postJson('/api/v1/invoices', [], $headers)->assertStatus(422)->assertJsonValidationErrors(['customer_id','items']);
});

it('can record payment and create transaction transfer via API', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);
    $headers = ['Authorization' => "Bearer {$token}"];

    // Create prerequisites via portal factories in company context
    $customer = Customer::factory()->create(['company_id' => $company->id]);
    $account = BankAccount::factory()->create(['company_id' => $company->id, 'currency' => $company->currency]);
    $account2 = BankAccount::factory()->create(['company_id' => $company->id, 'currency' => $company->currency]);

    // Create invoice so payment has something to allocate? Payment can be unallocated though.
    $invoice = $this->postJson('/api/v1/invoices', [
        'customer_id' => $customer->id,
        'issue_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'items' => [['description' => 'Item','quantity'=>1,'unit_price'=>'100.00']],
    ], $headers)->json();

    $payRes = $this->postJson('/api/v1/payments', [
        'customer_id' => $customer->id,
        'bank_account_id' => $account->id,
        'amount' => 5000,
        'date' => now()->toDateString(),
        'method' => 'bank_transfer',
        'allocations' => [['invoice_id' => $invoice['id'], 'amount' => 5000]],
    ], $headers);
    $payRes->assertCreated();

    // transfer
    $this->postJson('/api/v1/transactions/transfer', [
        'from_account_id' => $account->id,
        'to_account_id' => $account2->id,
        'amount' => 2000,
        'date' => now()->toDateString(),
    ], $headers)->assertCreated();
});

it('expenses API creates and voids', function () {
    [$user, $company] = userWithCompany('owner');
    $token = apiTokenForCompany($company, $user);
    $headers = ['Authorization' => "Bearer {$token}"];

    $cat = ExpenseCategory::factory()->create(['company_id' => $company->id]);
    $acc = BankAccount::factory()->create(['company_id' => $company->id]);

    $res = $this->postJson('/api/v1/expenses', [
        'expense_category_id' => $cat->id,
        'bank_account_id' => $acc->id,
        'amount' => 2500,
        'date' => now()->toDateString(),
        'description' => 'Office supplies',
    ], $headers);
    $res->assertCreated();
    $id = $res->json('id');

    $this->postJson("/api/v1/expenses/{$id}/void", ['reason' => 'Duplicate'], $headers)->assertOk();
});

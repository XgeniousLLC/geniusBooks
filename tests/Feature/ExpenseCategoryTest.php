<?php

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\CompanyProvisioningService;

it('seeds default categories when a company is created', function () {
    $owner = \App\Models\User::factory()->create();

    $company = app(CompanyProvisioningService::class)->createFor($owner, [
        'name' => 'Acme', 'currency' => 'USD',
    ]);

    $count = ExpenseCategory::withoutCompanyScope()->where('company_id', $company->id)->count();

    expect($count)->toBe(count(ExpenseCategory::DEFAULTS));
});

it('adds a custom category and rejects duplicates', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/expenses/categories', ['name' => 'Subscriptions'])->assertRedirect();
    $this->assertDatabaseHas('expense_categories', ['company_id' => $company->id, 'name' => 'Subscriptions']);

    $this->post('/portal/expenses/categories', ['name' => 'Subscriptions'])->assertSessionHasErrors('name');
});

it('refuses to delete an in-use category', function () {
    [$user, $company] = userWithCompany('owner');
    $category = ExpenseCategory::factory()->for($company)->create(['name' => 'Hosting']);
    Expense::factory()->for($company)->create(['expense_category_id' => $category->id]);

    actingAsCompany($user, $company);

    $this->delete("/portal/expenses/categories/{$category->id}")
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('expense_categories', ['id' => $category->id, 'deleted_at' => null]);
});

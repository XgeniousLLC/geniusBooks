<?php

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Transaction;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function expensePayload(array $overrides = []): array
{
    return array_merge([
        'date' => now()->toDateString(),
        'amount' => '150.00',
        'description' => 'AWS hosting',
        'is_recurring' => false,
    ], $overrides);
}

it('records an expense, posts the ledger and reduces the account balance', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 100000]);
    $category = ExpenseCategory::factory()->for($company)->create(['name' => 'Software']);
    $vendor = Vendor::factory()->for($company)->create(['name' => 'AWS']);

    actingAsCompany($user, $company);

    $this->post('/portal/expenses', expensePayload([
        'bank_account_id' => $account->id,
        'expense_category_id' => $category->id,
        'vendor_id' => $vendor->id,
    ]))->assertRedirect();

    $expense = Expense::first();

    expect($expense->amount)->toBe(15000)
        ->and($expense->transaction_id)->not->toBeNull()
        ->and($account->balance())->toBe(85000)
        ->and(Transaction::where('type', 'expense')->where('direction', 'out')->sum('amount'))->toBe(15000);
});

it('reverses and reposts the ledger when an expense is updated', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 100000]);

    actingAsCompany($user, $company);

    $this->post('/portal/expenses', expensePayload(['bank_account_id' => $account->id, 'amount' => '100.00']));
    $expense = Expense::first();

    $this->put("/portal/expenses/{$expense->id}", expensePayload(['bank_account_id' => $account->id, 'amount' => '30.00']));

    $net = Transaction::get()->sum(fn (Transaction $transaction) => $transaction->signedAmount());

    expect($account->balance())->toBe(97000)
        ->and($net)->toBe(-3000);
});

it('voids an expense and reverses the ledger', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 50000]);

    actingAsCompany($user, $company);

    $this->post('/portal/expenses', expensePayload(['bank_account_id' => $account->id, 'amount' => '200.00']));
    $expense = Expense::first();

    $this->post("/portal/expenses/{$expense->id}/void", ['reason' => 'Duplicate'])->assertRedirect();

    expect($expense->fresh()->isVoided())->toBeTrue()
        ->and($account->balance())->toBe(50000);
});

it('validates expense input', function () {
    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/expenses', ['date' => '', 'amount' => '-5', 'description' => ''])
        ->assertSessionHasErrors(['date', 'amount', 'description']);
});

it('stores and serves an attachment safely', function () {
    Storage::fake('local');

    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $this->post('/portal/expenses', expensePayload([
        'bank_account_id' => $account->id,
        'attachment' => UploadedFile::fake()->image('receipt.png'),
    ]))->assertRedirect();

    $expense = Expense::first();

    expect($expense->attachment_path)->not->toBeNull();
    Storage::disk('local')->assertExists($expense->attachment_path);

    $this->get("/portal/expenses/{$expense->id}/attachment")->assertOk();
});

it('rejects a disallowed attachment type', function () {
    Storage::fake('local');

    [$user, $company] = userWithCompany('owner');
    actingAsCompany($user, $company);

    $this->post('/portal/expenses', expensePayload([
        'attachment' => UploadedFile::fake()->create('script.exe', 10),
    ]))->assertSessionHasErrors('attachment');
});

it('filters expenses by category and search', function () {
    [$user, $company] = userWithCompany('owner');
    $software = ExpenseCategory::factory()->for($company)->create(['name' => 'Software']);
    $travel = ExpenseCategory::factory()->for($company)->create(['name' => 'Travel']);
    Expense::factory()->for($company)->create(['description' => 'AWS', 'expense_category_id' => $software->id]);
    Expense::factory()->for($company)->create(['description' => 'Flight', 'expense_category_id' => $travel->id]);

    actingAsCompany($user, $company);

    $this->get("/portal/expenses?expense_category_id={$software->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Expenses/Index')->has('expenses.data', 1));

    $this->get('/portal/expenses?search=Flight')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('expenses.data.0.description', 'Flight'));
});

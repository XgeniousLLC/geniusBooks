<?php

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Http\UploadedFile;

function bankCsv(array $rows): UploadedFile
{
    $content = implode("\n", array_map(fn ($row) => implode(',', $row), $rows));

    return UploadedFile::fake()->createWithContent('statement.csv', $content);
}

it('imports bank statement lines from csv', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $file = bankCsv([
        ['date', 'description', 'amount', 'reference'],
        ['2026-01-02', 'Client deposit', '100.00', 'REF1'],
        ['2026-01-03', 'Software', '-25.50', ''],
    ]);

    $this->post("/portal/accounts/{$account->id}/reconcile/import", ['file' => $file])->assertRedirect();

    $lines = BankStatementLine::where('bank_account_id', $account->id)->orderBy('date')->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->amount)->toBe(10000)
        ->and($lines[1]->amount)->toBe(-2550);
});

it('aborts the import when a row is invalid', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($user, $company);

    $file = bankCsv([
        ['date', 'description', 'amount'],
        ['2026-01-02', 'Valid', '10.00'],
        ['', 'Broken', 'notanumber'],
    ]);

    $this->post("/portal/accounts/{$account->id}/reconcile/import", ['file' => $file])
        ->assertSessionHas('importErrors');

    expect(BankStatementLine::count())->toBe(0);
});

it('auto-matches statement lines to ledger transactions', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create();

    app(LedgerPostingService::class)->in($company, $account, 10000, TransactionType::Income->value, 'Client deposit', now(), null);

    BankStatementLine::factory()->for($company)->create([
        'bank_account_id' => $account->id, 'amount' => 10000, 'date' => now()->toDateString(),
    ]);

    actingAsCompany($user, $company);

    $this->post("/portal/accounts/{$account->id}/reconcile/auto-match")->assertRedirect();

    $line = BankStatementLine::first();
    expect($line->matched_transaction_id)->not->toBeNull();
});

it('creates a ledger transaction from an unmatched line and reconciles it', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create(['opening_balance' => 50000]);

    $line = BankStatementLine::factory()->for($company)->create([
        'bank_account_id' => $account->id, 'amount' => -5000, 'date' => now()->toDateString(),
    ]);

    actingAsCompany($user, $company);

    $this->post("/portal/accounts/{$account->id}/reconcile/{$line->id}/create-transaction")->assertRedirect();

    $line->refresh();

    expect($line->matched_transaction_id)->not->toBeNull()
        ->and($line->reconciled_at)->not->toBeNull()
        ->and($account->balance())->toBe(45000)
        ->and(Transaction::where('type', 'adjustment')->count())->toBe(1);
});

it('requires a match before reconciling', function () {
    [$user, $company] = userWithCompany('owner');
    $account = BankAccount::factory()->for($company)->create();
    $line = BankStatementLine::factory()->for($company)->create(['bank_account_id' => $account->id]);

    actingAsCompany($user, $company);

    $this->post("/portal/accounts/{$account->id}/reconcile/{$line->id}/reconcile", ['reconciled' => true])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect($line->fresh()->reconciled_at)->toBeNull();
});

it('forbids staff from reconciliation', function () {
    [$staff, $company] = userWithCompany('staff');
    $account = BankAccount::factory()->for($company)->create();

    actingAsCompany($staff, $company);

    $this->get("/portal/accounts/{$account->id}/reconcile")->assertForbidden();
});

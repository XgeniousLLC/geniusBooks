<?php

use App\Enums\TransactionType;
use App\Models\BankAccount;
use App\Models\Company;
use App\Services\Accounting\LedgerPostingService;

function account(int $opening = 0): BankAccount
{
    return BankAccount::factory()->create(['opening_balance' => $opening]);
}

it('derives balance from opening balance and ledger movements', function () {
    $ledger = app(LedgerPostingService::class);
    $company = Company::factory()->create();
    $bank = account(10000);

    $ledger->in($company, $bank, 5000, TransactionType::Income->value, 'Direct income', now(), null);
    $ledger->out($company, $bank, 2000, TransactionType::Expense->value, 'Software', now(), null);

    expect($bank->balance())->toBe(13000);
});

it('conserves total across a transfer', function () {
    $ledger = app(LedgerPostingService::class);
    $company = Company::factory()->create();
    $from = account(50000);
    $to = account(0);

    $ledger->transfer($company, $from, $to, 20000, 'Move to savings', now());

    expect($from->balance())->toBe(30000)
        ->and($to->balance())->toBe(20000)
        ->and($from->balance() + $to->balance())->toBe(50000);
});

it('reverses a transaction with a counter entry', function () {
    $ledger = app(LedgerPostingService::class);
    $company = Company::factory()->create();
    $bank = account(0);

    $transaction = $ledger->in($company, $bank, 5000, TransactionType::Income->value, 'Income', now(), null);
    $ledger->reverse($transaction, 'Entered in error');

    expect($bank->balance())->toBe(0);
});

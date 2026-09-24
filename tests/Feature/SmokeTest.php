<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Vendor;

/**
 * Renders every GET page as a company owner. Guards against runtime type
 * errors (e.g. calling a Builder method on a Collection) that unit coverage
 * misses because a page was never visited.
 */
it('renders every portal page without server errors', function () {
    [$owner, $company] = userWithCompany('owner');

    $customer = Customer::factory()->for($company)->create();
    $product = Product::factory()->for($company)->create();
    $account = BankAccount::factory()->for($company)->create();
    $vendor = Vendor::factory()->for($company)->create();
    $category = ExpenseCategory::factory()->for($company)->create(['name' => 'Software']);

    $sent = Invoice::factory()->for($company)->for($customer)->sent()->create(['total' => 10000, 'subtotal' => 10000]);
    $draft = Invoice::factory()->for($company)->for($customer)->create();
    $quote = Quote::factory()->for($company)->for($customer)->create();
    $payment = Payment::factory()->for($company)->for($customer)->create();
    $expense = Expense::factory()->for($company)->create(['vendor_id' => $vendor->id, 'expense_category_id' => $category->id]);
    $ledger = LedgerAccount::factory()->for($company)->create();

    actingAsCompany($owner, $company);

    $urls = [
        '/portal',
        '/portal/customers', '/portal/customers/create', "/portal/customers/{$customer->id}", "/portal/customers/{$customer->id}/edit", '/portal/customers/import',
        '/portal/products', '/portal/products/create', "/portal/products/{$product->id}/edit", '/portal/products/import',
        '/portal/invoices', '/portal/invoices/create', "/portal/invoices/{$sent->id}", "/portal/invoices/{$draft->id}/edit", "/portal/invoices/{$sent->id}/pdf",
        '/portal/quotes', '/portal/quotes/create', "/portal/quotes/{$quote->id}", "/portal/quotes/{$quote->id}/edit",
        '/portal/payments', '/portal/payments/create', "/portal/payments/{$payment->id}",
        '/portal/credit-notes',
        '/portal/expenses', '/portal/expenses/create', "/portal/expenses/{$expense->id}", "/portal/expenses/{$expense->id}/edit", '/portal/expenses/categories',
        '/portal/vendors', '/portal/vendors/create', "/portal/vendors/{$vendor->id}/edit",
        '/portal/accounts', '/portal/accounts/create', "/portal/accounts/{$account->id}", "/portal/accounts/{$account->id}/edit", "/portal/accounts/{$account->id}/reconcile",
        '/portal/chart-of-accounts', '/portal/chart-of-accounts/create', "/portal/chart-of-accounts/{$ledger->id}/edit",
        '/portal/transactions', '/portal/transactions/create',
        '/portal/reports', '/portal/reports/profit-and-loss', '/portal/reports/income', '/portal/reports/expenses',
        '/portal/reports/receivables', '/portal/reports/tax-summary', "/portal/reports/customers/{$customer->id}/statement",
        '/portal/settings/business', '/portal/settings/invoices', '/portal/settings/tax', '/portal/settings/email', '/portal/settings/payments', '/portal/settings/users',
        '/portal/search?q=acme',
        '/portal/profile',
    ];

    foreach ($urls as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('renders every portal page for an accountant', function () {
    [$accountant, $company] = userWithCompany('accountant');
    $customer = Customer::factory()->for($company)->create();

    actingAsCompany($accountant, $company);

    foreach (['/portal', '/portal/customers', '/portal/invoices', '/portal/payments', '/portal/expenses', '/portal/reports', '/portal/chart-of-accounts', '/portal/transactions'] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

<?php

namespace App\Providers;

use App\Enums\CompanyRole;
use App\Enums\Permission;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\BankAccountPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\InvitationPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\LedgerAccountPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotePolicy;
use App\Policies\TransactionPolicy;
use App\Policies\VendorPolicy;
use App\Support\CompanyContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CompanyContext::class);
        $this->app->singleton(\App\Services\Sms\SmsManager::class);
    }

    public function boot(): void
    {
        RateLimiter::for('portal-auth', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        foreach (Permission::all() as $ability) {
            Gate::define($ability, function (User $user) use ($ability) {
                if (! app(CompanyContext::class)->has()) {
                    return false;
                }

                return CompanyRole::anyCan($user->getRoleNames(), $ability);
            });
        }

        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(LedgerAccount::class, LedgerAccountPolicy::class);
        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Quote::class, QuotePolicy::class);
    }
}

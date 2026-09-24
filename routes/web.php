<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CompanyController as AdminCompanyController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PageController as AdminPageController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Portal\AuthController as PortalAuthController;
use App\Http\Controllers\Portal\BankAccountController;
use App\Http\Controllers\Portal\ChartOfAccountsController;
use App\Http\Controllers\Portal\CompanySwitchController;
use App\Http\Controllers\Portal\CreditNoteController;
use App\Http\Controllers\Portal\CustomerController;
use App\Http\Controllers\Portal\DashboardController as PortalDashboardController;
use App\Http\Controllers\Portal\EmailSettingsController;
use App\Http\Controllers\Portal\EmailVerificationController;
use App\Http\Controllers\Portal\ExpenseCategoryController;
use App\Http\Controllers\Portal\ExpenseController;
use App\Http\Controllers\Portal\ImpersonationController;
use App\Http\Controllers\Portal\ImportController;
use App\Http\Controllers\Portal\InvitationAcceptController;
use App\Http\Controllers\Portal\InvitationController;
use App\Http\Controllers\Portal\InvoiceController;
use App\Http\Controllers\Portal\MembersController;
use App\Http\Controllers\Portal\OnboardingController;
use App\Http\Controllers\Portal\PaymentController;
use App\Http\Controllers\Portal\ProductController;
use App\Http\Controllers\Portal\ProfileController as PortalProfileController;
use App\Http\Controllers\Portal\ReportController;
use App\Http\Controllers\Portal\SearchController;
use App\Http\Controllers\Portal\SettingsController;
use App\Http\Controllers\Portal\TransactionController;
use App\Http\Controllers\Portal\VendorController;
use Illuminate\Support\Facades\Route;

// Customer Portal
Route::prefix('portal')->name('portal.')->group(function () {
    // Guest routes
    Route::middleware('guest.customer')->group(function () {
        Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [PortalAuthController::class, 'login'])->middleware('throttle:portal-auth');
        Route::get('/register', [PortalAuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [PortalAuthController::class, 'register'])->middleware('throttle:portal-auth');
        Route::get('/forgot-password', [PortalAuthController::class, 'showForgotPassword'])->name('password.request');
        Route::post('/forgot-password', [PortalAuthController::class, 'sendResetLink'])->name('password.email');
        Route::get('/reset-password/{token}', [PortalAuthController::class, 'showResetPassword'])->name('password.reset');
        Route::post('/reset-password', [PortalAuthController::class, 'resetPassword'])->name('password.update');
    });

    // Authenticated customer routes
    Route::middleware('customer')->group(function () {
        Route::post('/logout', [PortalAuthController::class, 'logout'])->name('logout');
        Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');

        Route::middleware('verified')->group(function () {
            Route::get('/suspended', fn () => inertia('Suspended'))->name('suspended');

            // Account deletion (self-service)
            Route::post('/account', [PortalProfileController::class, 'destroy'])->name('account.destroy');

            // Onboarding — reachable before a company exists.
            Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding');
            Route::post('/onboarding', [OnboardingController::class, 'store'])->name('onboarding.store');

            // Application routes — require an active company.
            Route::middleware('company')->group(function () {
                Route::get('/', [PortalDashboardController::class, 'index'])->name('home');
                Route::post('/company/switch', CompanySwitchController::class)->name('company.switch');

                // Add another business to the account
                Route::get('/businesses/create', [OnboardingController::class, 'create'])->name('businesses.create');
                Route::post('/businesses/create', [OnboardingController::class, 'storeBusiness'])->name('businesses.store');

                Route::get('/profile', [PortalProfileController::class, 'edit'])->name('profile.edit');
                Route::patch('/profile', [PortalProfileController::class, 'update'])->name('profile.update');
                Route::patch('/profile/password', [PortalProfileController::class, 'updatePassword'])->name('profile.password');

                // Sales — customers
                Route::get('/customers/import', [ImportController::class, 'customers'])->name('imports.customers');
                Route::post('/customers/import', [ImportController::class, 'importCustomers'])->name('imports.customers.store');
                Route::get('/customers/import/template', [ImportController::class, 'customersTemplate'])->name('imports.customers.template');
                Route::post('/customers/bulk', [CustomerController::class, 'bulk'])->name('customers.bulk');
                Route::resource('customers', CustomerController::class);

                // Sales — products & services
                Route::get('/products/import', [ImportController::class, 'products'])->name('imports.products');
                Route::post('/products/import', [ImportController::class, 'importProducts'])->name('imports.products.store');
                Route::get('/products/import/template', [ImportController::class, 'productsTemplate'])->name('imports.products.template');
                Route::post('/products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
                Route::resource('products', ProductController::class)->except('show');

                // Sales — invoices
                Route::get('/invoices', [InvoiceController::class, 'index'])->name('invoices.index');
                Route::get('/invoices/create', [InvoiceController::class, 'create'])->name('invoices.create');
                Route::post('/invoices', [InvoiceController::class, 'store'])->name('invoices.store');
                Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
                Route::get('/invoices/{invoice}/edit', [InvoiceController::class, 'edit'])->name('invoices.edit');
                Route::put('/invoices/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
                Route::delete('/invoices/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
                Route::post('/invoices/{invoice}/duplicate', [InvoiceController::class, 'duplicate'])->name('invoices.duplicate');
                Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'markSent'])->name('invoices.send');
                Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
                Route::post('/invoices/{invoice}/email', [InvoiceController::class, 'sendEmail'])->name('invoices.email');
                Route::get('/invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');

                // Settings — email & invoice delivery
                Route::get('/settings/email', [EmailSettingsController::class, 'index'])->name('settings.email');
                Route::patch('/settings/email', [EmailSettingsController::class, 'update'])->name('settings.email.update');
                Route::post('/settings/email/templates', [EmailSettingsController::class, 'updateTemplate'])->name('settings.email.templates');
                Route::post('/settings/email/test', [EmailSettingsController::class, 'testSend'])->name('settings.email.test');

                // Sales — payments
                Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
                Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
                Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
                Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
                Route::post('/payments/{payment}/email', [PaymentController::class, 'emailReceipt'])->name('payments.email');
                Route::post('/payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

                // Sales — credit notes
                Route::get('/credit-notes', [CreditNoteController::class, 'index'])->name('credit-notes.index');
                Route::post('/invoices/{invoice}/credit-notes', [CreditNoteController::class, 'store'])->name('invoices.credit-notes');

                // Accounting — accounts
                Route::resource('accounts', BankAccountController::class);

                // Expenses
                Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
                Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
                Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
                Route::get('/expenses/categories', [ExpenseCategoryController::class, 'index'])->name('expenses.categories');
                Route::post('/expenses/categories', [ExpenseCategoryController::class, 'store'])->name('expenses.categories.store');
                Route::delete('/expenses/categories/{category}', [ExpenseCategoryController::class, 'destroy'])->name('expenses.categories.destroy');
                Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
                Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
                Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
                Route::post('/expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
                Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'attachment'])->name('expenses.attachment');

                // Vendors
                Route::resource('vendors', VendorController::class)->except('show');

                // Accounting — chart of accounts
                Route::get('/chart-of-accounts', [ChartOfAccountsController::class, 'index'])->name('chart-of-accounts.index');
                Route::get('/chart-of-accounts/create', [ChartOfAccountsController::class, 'create'])->name('chart-of-accounts.create');
                Route::post('/chart-of-accounts', [ChartOfAccountsController::class, 'store'])->name('chart-of-accounts.store');
                Route::get('/chart-of-accounts/{account}/edit', [ChartOfAccountsController::class, 'edit'])->name('chart-of-accounts.edit');
                Route::put('/chart-of-accounts/{account}', [ChartOfAccountsController::class, 'update'])->name('chart-of-accounts.update');
                Route::delete('/chart-of-accounts/{account}', [ChartOfAccountsController::class, 'destroy'])->name('chart-of-accounts.destroy');

                // Accounting — transactions
                Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
                Route::get('/transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
                Route::post('/transactions/transfer', [TransactionController::class, 'storeTransfer'])->name('transactions.transfer');
                Route::post('/transactions/adjustment', [TransactionController::class, 'storeAdjustment'])->name('transactions.adjustment');
                Route::post('/transactions/income', [TransactionController::class, 'storeIncome'])->name('transactions.income');
                Route::post('/transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])->name('transactions.reverse');

                // Reports
                Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
                Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('reports.profit-and-loss');
                Route::get('/reports/income', [ReportController::class, 'income'])->name('reports.income');
                Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
                Route::get('/reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
                Route::get('/reports/tax-summary', [ReportController::class, 'taxSummary'])->name('reports.tax-summary');
                Route::get('/reports/customers/{customer}/statement', [ReportController::class, 'statement'])->name('reports.statement');
                Route::post('/reports/customers/{customer}/statement/email', [ReportController::class, 'emailStatement'])->name('reports.statement.email');

                // Customer opening balance
                Route::post('/customers/{customer}/opening-balance', [CustomerController::class, 'storeOpeningBalance'])->name('customers.opening-balance');

                // Settings — business, invoices, tax, export
                Route::get('/settings/business', [SettingsController::class, 'business'])->name('settings.business');
                Route::patch('/settings/business', [SettingsController::class, 'updateBusiness'])->name('settings.business.update');
                Route::get('/settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');
                Route::get('/settings/invoices', [SettingsController::class, 'invoices'])->name('settings.invoices');
                Route::patch('/settings/invoices', [SettingsController::class, 'updateInvoices'])->name('settings.invoices.update');
                Route::get('/settings/tax', [SettingsController::class, 'tax'])->name('settings.tax');
                Route::patch('/settings/tax', [SettingsController::class, 'updateTax'])->name('settings.tax.update');
                Route::get('/settings/export', [SettingsController::class, 'export'])->name('settings.export');

                // Global search
                Route::get('/search', [SearchController::class, 'index'])->name('search');

                // Team & roles
                Route::get('/settings/users', [MembersController::class, 'index'])->name('settings.users');
                Route::patch('/settings/members/{member}/role', [MembersController::class, 'updateRole'])->name('members.role');
                Route::patch('/settings/members/{member}/deactivate', [MembersController::class, 'deactivate'])->name('members.deactivate');
                Route::patch('/settings/members/{member}/reactivate', [MembersController::class, 'reactivate'])->name('members.reactivate');
                Route::delete('/settings/members/{member}', [MembersController::class, 'destroy'])->name('members.destroy');
                Route::post('/settings/invitations', [InvitationController::class, 'store'])->name('invitations.store');
                Route::delete('/settings/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
            });
        });
    });
});

// Invitation acceptance — public, token-based.
Route::prefix('portal')->name('portal.')->middleware('throttle:portal-auth')->group(function () {
    Route::get('/invitations/{token}', [InvitationAcceptController::class, 'show'])->name('invitations.accept');
    Route::post('/invitations/{token}', [InvitationAcceptController::class, 'accept'])->name('invitations.accept.store');
});

// Public invoice view — signature-protected, no authentication.
Route::prefix('portal')->name('portal.')->middleware(['signed', 'throttle:60,1'])->group(function () {
    Route::get('/invoices/public/{invoice}', [InvoiceController::class, 'public'])->name('invoices.public');
    Route::get('/invoices/public/{invoice}/pdf', [InvoiceController::class, 'publicPdf'])->name('invoices.public.pdf');
});

// Email verification — names must match Laravel conventions (no portal prefix).
Route::prefix('portal')->middleware('customer')->group(function () {
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware('throttle:6,1')->name('verification.send');
});

Route::get('/', function () {
    return redirect()->route('portal.login');
});

// Public legal pages
Route::inertia('/legal/terms', 'Legal/Terms')->name('legal.terms');
Route::inertia('/legal/privacy', 'Legal/Privacy')->name('legal.privacy');

// Frontend Routes
Route::get('/page/{page}', [PageController::class, 'show'])->name('page.show');

// Admin Authentication Routes
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Protected Admin Routes
    Route::middleware(['admin'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::resource('pages', AdminPageController::class);
        Route::post('/pages/analyze-seo', [AdminPageController::class, 'analyzeSEO'])->name('pages.analyze-seo');

        // Platform tenant management
        Route::get('/companies', [AdminCompanyController::class, 'index'])->name('companies.index');
        Route::get('/companies/{company}', [AdminCompanyController::class, 'show'])->name('companies.show');
        Route::post('/companies/{company}/suspend', [AdminCompanyController::class, 'suspend'])->name('companies.suspend');
        Route::post('/companies/{company}/reactivate', [AdminCompanyController::class, 'reactivate'])->name('companies.reactivate');
        Route::post('/companies/{company}/impersonate', [AdminCompanyController::class, 'impersonate'])->name('companies.impersonate');

        // Admin Management
        Route::resource('admins', AdminController::class);
        Route::post('/admins/{admin}/change-password', [AdminController::class, 'changePassword'])->name('admins.change-password');

        // Profile Management for Current Admin
        Route::get('/profile/edit', [AdminController::class, 'editProfile'])->name('profile.edit');
        Route::post('/profile/update', [AdminController::class, 'updateProfile'])->name('profile.update');
        Route::get('/profile/change-password', [AdminController::class, 'showChangePassword'])->name('profile.change-password');
        Route::post('/profile/change-password', [AdminController::class, 'updatePassword'])->name('profile.update-password');

        // User Management
        Route::resource('users', UserController::class);
        Route::post('/users/{user}/change-password', [UserController::class, 'changePassword'])->name('users.change-password');
    });
});

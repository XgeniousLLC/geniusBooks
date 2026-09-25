<?php

namespace Database\Seeders;

use App\Enums\InvoiceStatus;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\LedgerAccount;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Accounting\CreditNoteService;
use App\Services\Accounting\ExpenseService;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Accounting\PaymentService;
use App\Services\CompanyProvisioningService;
use App\Services\Invoicing\InvoiceService;
use App\Support\CompanyContext;
use App\Support\Money;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds the demonstration data:
 *  - ABC Digital Agency: the pristine Section 31 walkthrough (one invoice,
 *    one partial payment, one expense).
 *  - Nova Retail Ltd: a heavily populated second business so every module can
 *    be explored (multi-account, many customers/products/vendors, invoices in
 *    every status, payments/credits/refunds, expenses, ledger activity).
 *
 * Team logins: demo@<host> / password (owner), accountant@demo.test / password,
 * staff@demo.test / password.
 */
class DemoDataSeeder extends Seeder
{
    private CompanyProvisioningService $provisioning;

    public function run(): void
    {
        $this->provisioning = app(CompanyProvisioningService::class);

        $owner = $this->user(config('accounting.demo.email'), 'Demo Owner');
        $accountant = $this->user('accountant@demo.test', 'Priya Accountant');
        $staff = $this->user('staff@demo.test', 'Sam Staff');

        $abc = $this->seedSection31($owner, [$accountant, $staff]);

        $this->seedNova($owner, $accountant, $staff);

        $this->command?->info('Demo data ready. Logins: '.config('accounting.demo.email').' (owner) / accountant@demo.test / staff@demo.test — password: '.config('accounting.demo.password'));
    }

    private function user(string $email, string $name): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(config('accounting.demo.password')),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    /**
     * The minimal Section 31 journey (kept exact for the demo walkthrough).
     *
     * @param  list<User>  $members  additional team members to attach
     */
    private function seedSection31(User $owner, array $members = []): Company
    {
        $company = Company::firstOrCreate(
            ['name' => 'ABC Digital Agency'],
            [
                'email' => 'hello@abc-digital.test',
                'phone' => '+1 555 0100',
                'address' => '100 Market Street, Suite 300',
                'country' => 'US',
                'currency' => 'USD',
                'timezone' => 'UTC',
                'financial_year_start_month' => 1,
                'financial_year_start_day' => 1,
                'tax_registration_number' => 'US-0000000',
                'tax_inclusive' => false,
                'default_tax_rate' => 0,
                'invoice_prefix' => 'INV-',
                'invoice_number_padding' => 4,
                'default_payment_terms_days' => 15,
                'payment_instructions' => "Bank: First National\nAccount: 1234567890\nRouting: 021000021",
                'invoice_footer' => 'Thank you for your business.',
                'default_invoice_terms' => 'Payment due within 15 days.',
                'email_from_name' => 'ABC Digital Agency',
                'email_reply_to' => 'hello@abc-digital.test',
                'onboarded_at' => now(),
            ],
        );

        if (! $company->users()->whereKey($owner->id)->exists()) {
            $company->users()->attach($owner->id, ['is_active' => true]);
        }
        $this->provisioning->ensureRoles($company);
        $this->provisioning->ensureChartOfAccounts($company);
        $this->provisioning->ensureExpenseCategories($company);
        $this->provisioning->assignRole($company, $owner, 'owner');

        foreach ($members as $index => $member) {
            if (! $company->users()->whereKey($member->id)->exists()) {
                $company->users()->attach($member->id, ['is_active' => true]);
            }
            $this->provisioning->assignRole($company, $member, $index === 0 ? 'accountant' : 'staff');
        }

        if (Invoice::withoutCompanyScope()->where('company_id', $company->id)->exists()) {
            return $company;
        }

        app(CompanyContext::class)->set($company->id);

        $account = BankAccount::create([
            'name' => 'Business Bank', 'type' => 'bank', 'currency' => 'USD',
            'opening_balance' => 0, 'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'ABC Consulting',
            'company_name' => 'ABC Consulting LLC',
            'email' => 'billing@abc-consulting.test',
            'phone' => '+1 555 0199',
            'billing_address' => '500 Consulting Way',
            'currency' => 'USD',
            'payment_terms_days' => 15,
            'is_active' => true,
        ]);

        $product = Product::create([
            'name' => 'Business Consulting', 'sku' => 'CONSULT-01', 'type' => 'service',
            'unit_price' => 250000, 'tax_rate' => 0, 'category' => 'Consulting', 'is_active' => true,
        ]);

        $invoice = app(InvoiceService::class)->create($company, [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'terms' => 'Payment due within 15 days.',
            'items' => [[
                'product_id' => $product->id,
                'description' => 'Business Consulting',
                'quantity' => 1,
                'unit_price' => '2500',
                'tax_rate' => 0,
            ]],
        ]);

        $invoice->update(['status' => InvoiceStatus::Sent->value, 'sent_at' => now()]);

        app(PaymentService::class)->record($company, [
            'customer_id' => $customer->id,
            'bank_account_id' => $account->id,
            'date' => now()->toDateString(),
            'amount' => 150000,
            'method' => 'bank_transfer',
            'reference' => 'PAY-0001',
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => 150000]],
        ]);

        $software = ExpenseCategory::where('name', 'Software')->first();

        app(ExpenseService::class)->record($company, [
            'bank_account_id' => $account->id,
            'expense_category_id' => $software?->id,
            'date' => now()->toDateString(),
            'amount' => 30000,
            'tax_amount' => 0,
            'description' => 'Software Subscription',
            'is_recurring' => false,
        ]);

        Invitation::create([
            'company_id' => $company->id,
            'email' => 'new.team@abc-digital.test',
            'role' => 'staff',
            'expires_at' => now()->addDays(7),
        ]);

        app(CompanyContext::class)->forget();

        return $company;
    }

    /**
     * A heavily populated second tenant for exploring every module.
     */
    private function seedNova(User $owner, User $accountant, User $staff): void
    {
        $company = Company::firstOrCreate(
            ['name' => 'Nova Retail Ltd'],
            [
                'email' => 'accounts@novaretail.test',
                'phone' => '+44 20 7946 0000',
                'address' => '42 Commerce Road, London',
                'country' => 'GB',
                'currency' => 'USD',
                'timezone' => 'Europe/London',
                'financial_year_start_month' => 1,
                'financial_year_start_day' => 1,
                'tax_registration_number' => 'GB-123456789',
                'tax_inclusive' => false,
                'default_tax_rate' => 10,
                'invoice_prefix' => 'NOVA-',
                'invoice_number_padding' => 5,
                'default_payment_terms_days' => 30,
                'payment_instructions' => "Bank: Nova Business\nAccount: 99887766\nSort code: 00-00-00",
                'invoice_footer' => 'Nova Retail Ltd · Registered in England & Wales',
                'default_invoice_terms' => 'Payment due within 30 days.',
                'email_from_name' => 'Nova Retail',
                'email_reply_to' => 'accounts@novaretail.test',
                'onboarded_at' => now(),
            ],
        );

        // Always (re)link the current demo members — covers the case where the
        // database was imported from another environment and re-seeded.
        foreach ([$owner, $accountant, $staff] as $member) {
            if (! $company->users()->whereKey($member->id)->exists()) {
                $company->users()->attach($member->id, ['is_active' => true]);
            }
        }

        $this->provisioning->ensureRoles($company);
        app(\App\Services\EmailTemplateService::class)->ensureDefaults($company);
        $this->provisioning->ensureExpenseCategories($company);
        $this->provisioning->ensureChartOfAccounts($company);

        $this->provisioning->assignRole($company, $owner, 'owner');
        $this->provisioning->assignRole($company, $accountant, 'accountant');
        $this->provisioning->assignRole($company, $staff, 'staff');

        // The demo data itself is created once; the members above are re-linked
        // on every run so the configured demo account always has access.
        if (Invoice::withoutCompanyScope()->where('company_id', $company->id)->exists()) {
            return;
        }

        app(CompanyContext::class)->set($company->id);

        $ledger = app(LedgerPostingService::class);

        // Accounts
        $bank = BankAccount::create(['name' => 'Nova Business Bank', 'type' => 'bank', 'currency' => 'USD', 'opening_balance' => Money::fromDecimal('12500.00', 'USD')->amount()]);
        $cash = BankAccount::create(['name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'USD', 'opening_balance' => Money::fromDecimal('800.00', 'USD')->amount()]);
        $paypal = BankAccount::create(['name' => 'PayPal', 'type' => 'other', 'currency' => 'USD', 'opening_balance' => Money::fromDecimal('2140.00', 'USD')->amount()]);
        $accounts = [$bank, $cash, $paypal];

        // Customers
        $customerNames = [
            ['Brightline Studio', 'accounts@brightline.test'],
            ['Harbour & Co', 'ap@harbourco.test'],
            ['Maple Foods', 'finance@maplefoods.test'],
            ['Vertex Analytics', 'billing@vertex.test'],
            ['Copper Lane Cafe', 'hello@copperlane.test'],
            ['Orbit Media', 'pay@orbitmedia.test'],
            ['Fernwood Clinic', 'admin@fernwood.test'],
            ['Atlas Logistics', 'invoices@atlaslog.test'],
            ['Bluebird Interiors', 'accounts@bluebird.test'],
            ['Stonebridge Legal', 'billing@stonebridge.test'],
            ['Pinewood School', 'office@pinewood.test'],
            ['Lumen Software', 'ap@lumensoft.test'],
        ];
        $customers = collect($customerNames)->map(fn (array $c) => Customer::create([
            'name' => $c[0], 'company_name' => $c[0], 'email' => $c[1],
            'phone' => '+44 20 7946 '.random_int(1000, 9999),
            'billing_address' => random_int(1, 200).' High Street, London',
            'currency' => 'USD', 'payment_terms_days' => 30, 'is_active' => true,
        ]));

        // Products & services
        $productData = [
            ['Retail Display Unit', 'product', '249.00', 10, 'Hardware'],
            ['Point of Sale Licence', 'service', '99.00', 10, 'Software'],
            ['Installation Service', 'service', '150.00', 10, 'Services'],
            ['Extended Warranty', 'service', '75.00', 0, 'Services'],
            ['Barcode Scanner', 'product', '89.00', 10, 'Hardware'],
            ['Stock Audit', 'service', '320.00', 10, 'Services'],
            ['POS Training', 'service', '180.00', 10, 'Services'],
            ['Thermal Receipt Rolls (x50)', 'product', '35.00', 10, 'Supplies'],
            ['Loyalty Module', 'service', '120.00', 10, 'Software'],
            ['Shopfront Signage', 'product', '540.00', 10, 'Hardware'],
        ];
        $products = collect($productData)->map(fn (array $p, int $i) => Product::create([
            'name' => $p[0], 'sku' => 'NOVA-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
            'type' => $p[1], 'unit_price' => Money::fromDecimal($p[2], 'USD')->amount(),
            'tax_rate' => $p[3], 'category' => $p[4], 'is_active' => true,
        ]));

        // Vendors
        $vendors = collect([
            ['ScreenPrint Supplies', 'sales@screenprint.test'],
            ['CloudPOS Hosting', 'billing@cloudpos.test'],
            ['City Couriers', 'accounts@citycouriers.test'],
            ['OfficeMart', 'orders@officemart.test'],
            ['Nova Landlord Ltd', 'rent@novalandlord.test'],
            ['Spark Utilities', 'billing@sparkutilities.test'],
        ])->map(fn (array $v) => Vendor::create([
            'name' => $v[0], 'email' => $v[1], 'phone' => '+44 20 7946 0000',
            'tax_id' => 'GB-'.random_int(100000000, 999999999), 'is_active' => true,
        ]));

        $categories = ExpenseCategory::pluck('id', 'name');
        $software = LedgerAccount::where('code', '4100')->first();

        // ── Invoices in every status ─────────────────────────────────────────
        $invoices = collect();

        // 6 fully paid invoices across recent months.
        foreach (range(5, 0) as $monthsAgo) {
            $customer = $customers->random();
            $product = $products->random();
            $issue = now()->subMonths($monthsAgo)->startOfMonth()->addDays(random_int(1, 20));
            $invoice = $this->invoice($company, $customer, $product, $issue, $issue->copy()->addDays(14));
            $this->send($invoice, $issue->copy()->addDays(random_int(0, 3)));
            $this->pay($company, $invoice, (float) Money::of((int) $invoice->total, 'USD')->toDecimal(), $accounts[array_rand($accounts)], $issue->copy()->addDays(random_int(3, 20)));
            $invoices->push($invoice->refresh());
        }

        // 4 partially paid invoices.
        foreach (range(1, 4) as $i) {
            $customer = $customers->random();
            $product = $products->random();
            $issue = now()->subDays(random_int(20, 90));
            $invoice = $this->invoice($company, $customer, $product, $issue, $issue->copy()->addDays(30));
            $this->send($invoice, $issue->copy()->addDays(1));
            $half = Money::of((int) $invoice->total, 'USD')->multiply(0.4)->amount();
            $this->pay($company, $invoice, (float) Money::of($half, 'USD')->toDecimal(), $accounts[array_rand($accounts)], $issue->copy()->addDays(random_int(5, 25)));
            $invoices->push($invoice->refresh());
        }

        // 4 outstanding (sent) invoices, one overdue.
        foreach (range(1, 4) as $i) {
            $customer = $customers->random();
            $product = $products->random();
            $overdue = $i === 1;
            $issue = $overdue ? now()->subDays(45) : now()->subDays(random_int(3, 15));
            $invoice = $this->invoice($company, $customer, $product, $issue, $overdue ? now()->subDays(10) : now()->addDays(20));
            $this->send($invoice, $issue->copy()->addDay());
            $invoices->push($invoice->refresh());
        }

        // 1 draft, 1 cancelled.
        $draft = $this->invoice($company, $customers->random(), $products->random(), now(), now()->addDays(30));
        $invoices->push($draft);

        $cancelled = $this->invoice($company, $customers->random(), $products->random(), now()->subDays(5), now()->addDays(25));
        $cancelled->update(['status' => InvoiceStatus::Cancelled->value, 'cancelled_at' => now(), 'cancel_reason' => 'Order cancelled by customer']);
        $invoices->push($cancelled->refresh());

        // One multi-invoice allocation payment.
        $openTwo = $invoices->filter(fn (Invoice $inv) => in_array($inv->status, ['sent', 'viewed'], true) && $inv->balance() > 0)->take(2);
        if ($openTwo->count() === 2) {
            [$a, $b] = [$openTwo->values()[0], $openTwo->values()[1]];
            app(PaymentService::class)->record($company, [
                'customer_id' => $a->customer_id,
                'bank_account_id' => $bank->id,
                'date' => now()->subDays(2)->toDateString(),
                'amount' => $a->balance() + $b->balance(),
                'method' => 'bank_transfer',
                'reference' => 'PAY-BATCH-01',
                'allocations' => [
                    ['invoice_id' => $a->id, 'amount' => $a->balance()],
                    ['invoice_id' => $b->id, 'amount' => $b->balance()],
                ],
            ]);
        }

        // An overpayment that leaves customer credit.
        $creditCustomer = $customers->first();
        $creditInvoice = $this->invoice($company, $creditCustomer, $products->random(), now()->subDays(10), now()->addDays(20));
        $this->send($creditInvoice, now()->subDays(9));
        app(PaymentService::class)->record($company, [
            'customer_id' => $creditCustomer->id,
            'bank_account_id' => $bank->id,
            'date' => now()->subDays(5)->toDateString(),
            'amount' => $creditInvoice->total + Money::fromDecimal('150.00', 'USD')->amount(),
            'method' => 'bank_transfer',
            'reference' => 'PAY-OVER-01',
            'allocations' => [['invoice_id' => $creditInvoice->id, 'amount' => $creditInvoice->total]],
        ]);

        // A voided payment.
        $voidInvoice = $this->invoice($company, $customers->random(), $products->random(), now()->subDays(8), now()->addDays(22));
        $this->send($voidInvoice, now()->subDays(7));
        $voidPayment = $this->pay($company, $voidInvoice, 120.00, $cash, now()->subDays(4));
        app(PaymentService::class)->void($voidPayment, 'Recorded against the wrong invoice');

        // Credit notes: one applied, one refunded (pick fresh invoices with room).
        $openInvoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Sent->value, InvoiceStatus::Viewed->value])
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->balance() >= Money::fromDecimal('100.00', 'USD')->amount())
            ->values();

        if ($openInvoices->count() >= 2) {
            app(CreditNoteService::class)->issue($company, [
                'invoice_id' => $openInvoices[0]->id,
                'issue_date' => now()->subDays(3)->toDateString(),
                'amount' => Money::fromDecimal('25.00', 'USD')->amount(),
                'reason' => 'Goodwill discount',
            ]);
            app(CreditNoteService::class)->issue($company, [
                'invoice_id' => $openInvoices[1]->id,
                'issue_date' => now()->subDays(2)->toDateString(),
                'amount' => Money::fromDecimal('60.00', 'USD')->amount(),
                'reason' => 'Returned goods',
                'bank_account_id' => $bank->id,
            ]);
        }

        // Customer opening balance (ledger entry).
        $openingCustomer = $customers->last();
        $receivable = LedgerAccount::where('code', '1300')->first();
        $ledger->post($company, [
            'bank_account_id' => null,
            'ledger_account_id' => $receivable?->id,
            'type' => 'adjustment',
            'direction' => 'in',
            'amount' => Money::fromDecimal('420.00', 'USD')->amount(),
            'currency' => 'USD',
            'description' => 'Opening balance for '.$openingCustomer->name,
            'occurred_on' => now()->subMonths(6)->toDateString(),
            'source' => $openingCustomer,
            'reason' => 'Opening balance',
        ]);

        // ── Expenses across categories and vendors ───────────────────────────
        $expenseData = [
            ['CloudPOS Hosting', 'Software', 'CloudPOS Hosting', '120.00', 10, true, 'monthly'],
            ['Office rent', 'Rent', 'Nova Landlord Ltd', '1800.00', 0, true, 'monthly'],
            ['Electricity', 'Utilities', 'Spark Utilities', '240.50', 5, false, null],
            ['Courier charges', 'Travel', 'City Couriers', '85.00', 0, false, null],
            ['Stationery', 'Office', 'OfficeMart', '64.20', 20, false, null],
            ['Print materials', 'Advertising', 'ScreenPrint Supplies', '430.00', 20, false, null],
            ['POS hardware', 'Equipment', 'OfficeMart', '1290.00', 20, false, null],
        ];
        foreach ($expenseData as $index => $row) {
            app(ExpenseService::class)->record($company, [
                'bank_account_id' => $accounts[$index % count($accounts)]->id,
                'expense_category_id' => $categories[$row[1]] ?? null,
                'vendor_id' => $vendors->firstWhere('name', $row[2])?->id,
                'date' => now()->subDays(random_int(3, 120))->toDateString(),
                'amount' => Money::fromDecimal($row[3], 'USD')->amount(),
                'tax_amount' => Money::fromDecimal(
                    (string) round((float) $row[3] * $row[4] / 100, 2),
                    'USD'
                )->amount(),
                'description' => $row[0],
                'is_recurring' => $row[5],
                'recurrence_interval' => $row[6],
            ]);
        }

        // ── Ledger activity: direct income, adjustments, transfer ────────────
        $ledger->in($company, $bank, Money::fromDecimal('3200.00', 'USD')->amount(), 'income', 'Grant / other income', now()->subMonths(2), null, null, $software);
        $ledger->in($company, $paypal, Money::fromDecimal('450.00', 'USD')->amount(), 'income', 'Marketplace payout', now()->subDays(12), null, null, $software);
        $ledger->out($company, $cash, Money::fromDecimal('40.00', 'USD')->amount(), 'adjustment', 'Petty cash correction', now()->subDays(9), null, 'Till variance');
        $ledger->transfer($company, $bank, $paypal, Money::fromDecimal('500.00', 'USD')->amount(), 'Top up PayPal float', now()->subDays(6));

        app(CompanyContext::class)->forget();
    }

    private function invoice(Company $company, Customer $customer, Product $product, \Carbon\CarbonInterface $issue, \Carbon\CarbonInterface $due): Invoice
    {
        $quantity = random_int(1, 3);

        return app(InvoiceService::class)->create($company, [
            'customer_id' => $customer->id,
            'issue_date' => $issue->toDateString(),
            'due_date' => $due->toDateString(),
            'terms' => 'Payment due within 30 days.',
            'items' => [[
                'product_id' => $product->id,
                'description' => $product->name,
                'quantity' => $quantity,
                'unit_price' => Money::of((int) $product->unit_price, 'USD')->toDecimal(),
                'tax_rate' => (float) $product->tax_rate,
            ]],
        ]);
    }

    private function send(Invoice $invoice, \Carbon\CarbonInterface $date): void
    {
        $invoice->update([
            'status' => InvoiceStatus::Sent->value,
            'sent_at' => $date,
        ]);
    }

    private function pay(Company $company, Invoice $invoice, float $amountMajor, BankAccount $account, \Carbon\CarbonInterface $date): \App\Models\Payment
    {
        $minor = Money::fromDecimal(number_format($amountMajor, 2, '.', ''), $account->currency)->amount();

        return app(PaymentService::class)->record($company, [
            'customer_id' => $invoice->customer_id,
            'bank_account_id' => $account->id,
            'date' => $date->toDateString(),
            'amount' => $minor,
            'method' => 'bank_transfer',
            'reference' => 'PAY-'.Str::upper(Str::random(6)),
            'allocations' => [['invoice_id' => $invoice->id, 'amount' => $minor]],
        ]);
    }
}

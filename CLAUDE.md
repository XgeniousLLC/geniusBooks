# Xgenious Accounting

Multi-tenant, browser-based accounting and financial management SaaS. Each business
(tenant) gets an isolated workspace covering: **Business → Customers → Products →
Invoices → Payments → Expenses → Transactions → Reports**.

## Interfaces

- **Customer portal (React + Inertia)** at `/portal` — the accounting app itself.
- **Platform admin (Blade + Alpine)** at `/admin` — SaaS operator console: tenant list,
  suspend/reactivate, audited impersonation.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), Eloquent, Form Requests, Policies
- **Portal UI**: React 19 + TypeScript + Inertia + Vite + Tailwind CSS 4
- **Admin UI**: Laravel Blade + Alpine.js
- **Database**: MySQL (production) / SQLite (local default)
- **Packages**: `spatie/laravel-permission` (teams = company), `barryvdh/laravel-dompdf`
- **Integrations**: SMS via `App\Services\Sms\SmsManager` (drivers: log/twilio/vonage/http);
  online payments via Stripe (`App\Services\Payments\StripeGateway`, Laravel HTTP client, no SDK)
- **Queue / Scheduler**: Laravel Queue (database driver) + Supervisor + cron
- **Auth**: session guards — `admin` (platform) and `web` (tenant members)

## Architecture

- **Tenancy**: every domain table has `company_id`. Models use the
  `App\Models\Concerns\BelongsToCompany` trait (global scope + auto-stamp + cross-tenant
  guard). `App\Support\CompanyContext` is a request-scoped singleton set by the
  `company` middleware (`ResolveCurrentCompany`), which also sets the spatie permission
  team and the per-company money formatting.
- **Authorization**: three roles per company (owner/accountant/staff,
  `App\Enums\CompanyRole`) mapping to abilities (`App\Enums\Permission`); Gates +
  policies. UI hiding is never the only control.
- **Money**: `App\Support\Money` — integer minor units, half-up rounding, per-company
  symbol/position.
- **Invoicing**: `InvoiceCalculator` is the authoritative totals engine (line/invoice
  discounts, inclusive/exclusive tax); the client mirror is preview-only.
- **Ledger**: `LedgerPostingService` is the only writer of money movements (in/out,
  transfers, reversals). Balances and reports are derived; records are voided with a
  reason, never silently deleted.
- **Reporting**: `ReportService` (P&L, income, expenses, receivables, tax, statements)
  and `DashboardService`.

## Key Files

```
app/Http/Controllers/Portal/  # accounting app (Inertia) — one controller per module
app/Http/Controllers/Admin/   # platform console
app/Http/Middleware/          # ResolveCurrentCompany, AddRequestContext, AdminAuth, …
app/Models/                   # Company, Customer, Invoice, Payment, Expense, Transaction, …
app/Models/Concerns/          # BelongsToCompany, Auditable, Voidable
app/Policies/                 # one per model
app/Services/                 # Accounting, Invoicing, Reporting, Documents, Imports, Export, Sms, Payments
app/Support/                  # CompanyContext, Money, FinancialYear, ListQuery
app/Enums/                    # CompanyRole, Permission, InvoiceStatus, TransactionType, …
resources/js/pages/           # portal Inertia React pages
resources/views/              # admin blade, emails, documents (PDF), reports
routes/web.php                # all routes (portal + admin + public)
routes/console.php            # scheduler
docs/                         # SPASTRINT_TRACKER.md + HTML user/developer/deployment guides
```

## Commands

```bash
# Setup
composer install && npm install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite

# Database
php artisan migrate
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=SiteSettingsSeeder
php artisan db:seed --class=DemoDataSeeder   # Section 31 demo + rich second tenant

# Dev
php artisan serve
php artisan queue:work            # queued email
npm run dev

# Test / lint / build
php artisan test
./vendor/bin/pint
npm run lint
npm run build

# Integrations
php artisan mail:test you@example.com
php artisan sms:test +1234567890
```

## Demo Credentials

- **Portal owner**: `demo@<your-host>` / `password` (e.g. `demo@xgenious.com`)
- **Accountant / Staff**: `accountant@demo.test` / `staff@demo.test` / `password`
- **Platform admin**: `admin@example.com` / `password` at `/admin/login`

## Conventions

- Thin controllers (authorize → validate → service → Inertia/redirect). Validation in
  Form Requests; domain logic in services.
- Financial effects flow through the ledger posting service; no direct balance mutation.
- Financial records are voided (with reason) and audited, never hard-deleted.
- Money uses integer minor units; never trust the client's calculations.
- No emojis in UI, logs, or messages.
- Tests: Pest (`RefreshDatabase`), helpers `userWithCompany()` / `actingAsCompany()`,
  tenant-isolation harness `tests/Concerns/AssertsTenantIsolation`, and `SmokeTest`
  which renders every page.

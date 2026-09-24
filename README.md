# Xgenious Accounting

Multi-tenant, browser-based accounting and financial management SaaS. Each business
gets an isolated workspace covering the full core workflow:

**Business → Customers → Products/Services → Invoices → Payments → Expenses → Transactions → Reports**

## Interfaces

- **Customer portal** (`/portal`) — the accounting app. React 19 + TypeScript + Inertia.
- **Platform admin** (`/admin`) — SaaS operator console (tenant list, suspend/reactivate,
  audited impersonation). Laravel Blade + Alpine.js.

## Features

### Core
- Multi-tenant companies with isolated data, onboarding wizard, and a company switcher
  (including adding additional businesses).
- Roles per company — **Owner**, **Accountant**, **Staff** — enforced server-side.
- Team invitations with an accept flow, plus last-owner protection.

### Sales
- Customers (CRUD, CSV import, profile, statement, opening balances).
- Products & services (CRUD, CSV import).
- Invoices: authoritative totals engine (line/invoice discounts, inclusive/exclusive
  tax), status lifecycle, PDF, email delivery, signed public links, reminders,
  duplicate, cancel, and **recurring invoices**.
- Payments: partial/over/multi-invoice settlement, unapplied credit, idempotent
  recording, void with ledger reversal, receipts.
- Credit notes with optional refunds.

### Money & accounting
- Single-entry transaction ledger (posting service, transfers, reversals).
- Bank/cash accounts with derived balances and running history.
- Chart of accounts (Assets/Liabilities/Equity/Revenue/Expenses).
- Expenses with categories, vendors, attachments, recurrence, and ledger posting.

### Insight & admin
- Dashboard KPIs, charts and widgets.
- Reports: Profit & Loss, Income, Expenses, Receivables (aging), Tax Summary, and
  customer statements — all with date ranges and CSV/PDF export.
- Settings: business, currency (symbol and position), invoice, tax, and email
  (templates + notification toggles); global search; tenant data export; account
  deletion; legal pages.

## Documentation

HTML documentation lives in [`docs/`](docs/index.html):

- [User Manual](docs/user-manual.html) — how to use every module.
- [Developer Guide](docs/developer-guide.html) — architecture, setup, extending, testing.
- [Deployment Guide](docs/deployment-guide.html) — VPS, shared hosting, AWS and DigitalOcean.
- [`docs/SPRINT_TRACKER.md`](docs/SPRINT_TRACKER.md) — sprint/ticket history.

## Stack

- **Backend**: Laravel 12 (PHP 8.2+), Eloquent, Form Requests, Policies
- **Portal UI**: React 19 + TypeScript + Inertia + Vite + Tailwind CSS 4
- **Admin UI**: Laravel Blade + Alpine.js
- **Database**: MySQL (production) / SQLite (local default)
- **Packages**: `spatie/laravel-permission` (teams = company), `barryvdh/laravel-dompdf`
- **Queue / Scheduler**: Laravel Queue (database driver) + Supervisor + cron
- **Auth**: session guards — `admin` (platform) and `web` (tenant members)

## Project Structure

```
app/
├── Http/Controllers/{Portal,Admin}   # accounting app (Inertia) + platform console
├── Http/Middleware/                  # ResolveCurrentCompany, AddRequestContext, AdminAuth, …
├── Models/Concerns/                  # BelongsToCompany, Auditable, Voidable
├── Policies/                         # one per model
├── Services/                         # Accounting, Invoicing, Reporting, Documents, Imports, Export
├── Support/                          # CompanyContext, Money, FinancialYear, ListQuery
└── Enums/                            # CompanyRole, Permission, InvoiceStatus, …
resources/js/pages/                   # portal Inertia React pages
resources/views/                      # admin blade, emails, documents (PDF), reports
routes/web.php · routes/console.php
tests/                                # Feature, Unit, Concerns (tenant isolation)
docs/                                 # HTML guides + sprint tracker
```

## Installation & Setup

### Prerequisites
- PHP 8.2+ with `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`, `gd`
- Composer 2 & Node.js 20+
- SQLite (default) or MySQL

### Steps
```bash
composer install
npm install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

# Seed platform admin + optional demo data (Section 31 walkthrough + rich tenant)
php artisan db:seed --class=AdminSeeder
php artisan db:seed --class=SiteSettingsSeeder
php artisan db:seed --class=DemoDataSeeder

npm run build        # or: npm run dev
php artisan serve
php artisan queue:work
```

## Default Credentials

| Role | Login | Password |
|---|---|---|
| Portal owner | `demo@<your-host>` | `password` |
| Accountant | `accountant@demo.test` | `password` |
| Staff | `staff@demo.test` | `password` |
| Platform admin | `admin@example.com` (`/admin/login`) | `password` |

The demo accounts are created by `DemoDataSeeder`; the demo owner email derives from
`APP_URL` (override with `DEMO_EMAIL`).

## Routes

- **Portal**: `/portal` — login, register, password reset, dashboard, and all modules.
- **Admin**: `/admin` — login, dashboard, pages, admins, users, companies (tenants).
- **Public**: `GET /page/{page}`, `GET /legal/{terms,privacy}`; `/` redirects to portal login.

## Testing

```bash
php artisan test     # or ./vendor/bin/pest
./vendor/bin/pint    # PHP style
npm run lint         # TypeScript / React
```

The suite covers every module, tenant isolation, and a smoke test that renders every
page.

## Deploy

See the [Deployment Guide](docs/deployment-guide.html) for VPS, shared hosting, AWS and
DigitalOcean. Reference configs ship in `deploy/install.sh`, `deploy/nginx.conf`, and
`deploy/supervisor.conf`.

## License

Open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

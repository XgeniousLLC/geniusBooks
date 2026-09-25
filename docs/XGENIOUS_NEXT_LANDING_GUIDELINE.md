# Xgenious Next — Landing Page Guideline: GeniousBooks (Genius Accounting)

Create: `app/free-software/genius-books/page.tsx` (slug `genius-books`) + add tile to `app/free-software/page.tsx` listing. Follow exact pattern of `app/free-software/genius-hrm/page.tsx` (618 lines), `genius-crm`, `genius-school-management`, etc.

## 1. File Decisions

| Item | Value | Notes |
|---|---|---|
| **Route** | `/free-software/genius-books` | kebab, singular |
| **Display name** | **Genius Books** (or GeniousBooks) — use **Genius Accounting** as subtitle | Match xgenious.com URL `https://xgenious.com/free-software/genius-books` |
| **Component** | `export default function GeniusBooksPage()` | Next.js 15 App Router, server component |
| **Colors** | `COLOR = '#4f46e5'` (indigo), `LIGHT_COLOR = '#eef2ff'` | Distinct from HRM `#7c3aed`, CRM `#0ea5e9`, School `#059669` |
| **GITHUB_URL** | `https://github.com/XgeniousLLC/geniusBooks/archive/refs/tags/v1.0.0.zip` | Update tag when tagged; license server UUID needed for DownloadButton |
| **LICENSE_UUID** | Create new UUID via license server, e.g. `genius-books-uuid` | Register in `store/license` |
| **DEMO_URL** | `https://genius-books.xgenious.com/portal/login` (or current demo host) | Credentials: `demo@xgenious.com` / `password` |
| **DOCS_URL** | `https://genius-books-docs.vercel.app/` or `/docs` | Link to API docs + user manual |
| **Screenshots** | `/site-images/free-software/genius-books/*.png` | 6 images: dashboard, invoices, payments, expenses, transactions, reports |

## 2. SEO — `export const metadata: Metadata`

Title (55 ch): `Free Open Source Accounting Software: Laravel 13 + React 19`
Description (155 ch): `Download free self-hosted accounting SaaS. Multi-tenant, invoices, payments, expenses, ledger & reports. 10 finance integrations, REST API. MIT licensed.`
Canonical: `${BASE_URL}/free-software/genius-books`
OpenGraph title: `Free Open Source Accounting Software: Laravel 13 + React 19 | Xgenious`
Keywords (12):
```
free open source accounting software
self hosted accounting system
laravel accounting open source
free invoicing software
free bookkeeping software
multi-tenant accounting SaaS
invoice management system free download
expense management software free
accounting software with api
free accounting software with xero integration
quickbooks alternative self hosted
open source accounting MIT license
```

## 3. JSON-LD Schemas

**SoftwareApplication**:
```json
{
  "@context":"https://schema.org",
  "@type":"SoftwareApplication",
  "name":"Genius Books",
  "operatingSystem":"Linux, Windows, macOS",
  "applicationCategory":"BusinessApplication",
  "offers":{"@type":"Offer","price":"0","priceCurrency":"USD"},
  "description":"Free self-hosted multi-tenant accounting SaaS — invoices, payments, expenses, bank reconciliation, chart of accounts, ledger & reports. 10 finance integrations, REST API.",
  "url":"https://xgenious.com/free-software/genius-books",
  "author":{"@type":"Organization","name":"Xgenious","url":"https://xgenious.com"},
  "license":"https://opensource.org/licenses/MIT",
  "programmingLanguage":["PHP","TypeScript"]
}
```

**FAQPage** — 6 entries:
1. Is it really free? — MIT, no paid tier, unlimited companies/invoices.
2. Can I self-host on shared hosting? — Yes, PHP 8.4 + MySQL, works on cPanel with cron for queue.
3. Does it support multiple companies? — Yes, multi-tenant with company switcher, global scope isolation.
4. Can I sync with Xero/QuickBooks? — Yes, 10 providers two-way push/pull via Settings → Integrations.
5. Is there an API? — Yes, `/api/v1` Bearer tokens per company; see API documentation.
6. How to update? — `git pull && composer install && npm run build && php artisan migrate`.

## 4. Copy Blocks (use verbatim, adjust only names)

**Hero H1**: `Complete Open-Source Accounting SaaS: Free Forever`
Hero sub: `Manage customers, invoices, payments, expenses and reports in one self-hosted platform. Multi-tenant, audited ledger, 10 finance integrations and a full REST API. Built with Laravel 13 and React 19. No per-seat pricing. Your data on your server.`
Badges: `Free & Open Source • MIT License • Laravel 13 · React 19`
CTA: `DownloadButton label="Download Free, No Account Needed"` + secondary `Try Live Demo` + `Documentation`. Microcopy: `MIT License · No account required · No credit card · Unlimited businesses`.

**Stats bar** (6):
```
13+ Modules | 3 Roles (Owner/Accountant/Staff) | 10 Integrations | PHP 8.4+ | React 19 | Free Forever
```

**What is Genius Books?** (3 paragraphs, 80-90 words each) — describe as multi-tenant browser-based accounting SaaS, each business isolated workspace covering Business → Customers → Products → Quotes → Invoices → Payments → Expenses → Transactions → Reports. Emphasize ledger-posting service as single writer, integer minor units, void-with-reason. MIT, VPS/cPanel, forkable.

## 5. Feature List — 12 Modules (render as 3-col grid, same `MODULES` array shape as HRM)

Use this exact list for the landing page cards (title + 5-6 bullets each):

### A. Multi-Tenancy & Onboarding
- Unlimited businesses per install, isolated `company_id` scope via `BelongsToCompany` trait
- 3-step onboarding wizard: business details, currency & financial year, tax & invoice numbering
- Company switcher (+ New business) for accountants managing multiple clients
- Seeded chart of accounts, expense categories, email templates per company
- Session-scoped `CompanyContext`, spatie `team_id = company_id`

### B. Customers
- CRUD: name, company, email, phone, tax ID, billing/shipping, payment terms
- Search/filter by name/email/company, status Active/Archived, bulk archive/activate
- CSV import with `name` column template, all-or-nothing validation, duplicate skip
- Profile: Invoiced / Paid / Credited / Opening / Outstanding / Credit balance + recent invoices/payments
- Statement & opening balance (ledger-posted, not field mutation)

### C. Products & Services
- SKU unique per company, type product/service, unit price (minor units), tax rate, category
- Search by name/SKU, filter by type/category/status, bulk archive/activate
- CSV import with same validation rules
- Reusable in invoice/quote line items

### D. Quotes (Estimates)
- Same line-item engine as invoices, statuses Draft→Sent→Accepted/Declined/Expired
- One-click **Convert to draft invoice** (copies lines, new number via `DocumentNumberService`)
- Valid-until tracking

### E. Invoices — Authoritative Engine
- `InvoiceCalculator` (line subtotal → line discount → invoice discount → per-line tax inclusive/exclusive) — server is source of truth, client preview only
- Statuses: Draft, Sent, Viewed (first public-link open), Partially paid, Paid, Overdue (derived), Cancelled
- Actions: Edit (draft only), Mark sent, PDF download/print, Email (queued), Record payment, Credit note, Duplicate (new number), Cancel/void (never delete sent), Delete (draft)
- Signed public link (`/portal/invoices/public/{id}`) + public PDF, 30-day expiry
- Due-soon / overdue reminders (`invoices:send-reminders` daily, idempotent)
- Recurring invoices (weekly/monthly/yearly, atomic `document_sequences`)

### F. Payments & Credit
- Partial / over / multi-invoice settlement, unapplied credit, `payment_allocations` table
- Idempotent recording (`idempotency_key` per company, concurrent-safe)
- Void with ledger reversal + invoice recompute, receipt email
- Credit notes with optional refund out of bank account
- Customer credit visible on profile

### G. Expenses & Vendors
- 8 default categories + custom per company, in-use guard
- Vendors CRUD, address/tax ID, delete guard if has expenses
- Expense: date, amount (gross) + tax_amount (included), category, vendor, account, description, recurring flag, notes
- Receipt attachment PDF/JPG/PNG ≤10 MB, private `storage/app/private`, authorized download
- Recurring scheduler (`expenses:generate-recurring` daily, `next_recurrence_on`, idempotent), ledger out-posting, void with reversal
- Filters by category/vendor/account/date, filtered total header

### H. Bank / Cash Accounts & Reconciliation
- Account: name, type, opening balance (ledger-posted), currency, derived current balance + running history
- Import bank statement CSV (`date, description, amount, reference`), Auto-match by amount±3 days, manual match/unmatch, Create transaction, Reconcile toggle — audited

### I. Chart of Accounts & Ledger
- `ledger_accounts` Assets/Liabilities/Equity/Revenue/Expenses, parent/child hierarchy, code, type immutable after posting, delete guard if has transactions/sub-accounts
- `LedgerPostingService` — ONLY writer (in/out, paired transfers via `transfer_group` UUID, reversals via counter-entry)
- Transaction list: filters type/direction/account/ledger account/date/text, source drill-through
- Manual entries: Transfer, Adjustment, Direct income (to revenue account), void/reverse with reason

### J. Reports & Statements
- P&L, Income (by customer/month), Expenses (by category/vendor), Receivables (aging buckets), Tax Summary (collected/paid/taxable), General Ledger (opening/movements/closing), Customer statement (period CSV/PDF/email)
- Date range defaults to financial year, CSV/PDF export, financial-year boundaries from company settings

### K. API & Integrations
- **API applications**: `Settings → API applications` — `gb_`+60 token, SHA-256 stored, one-time show, expiry, `last_used_at`, `DELETE` revoke, company-scoped via `AuthenticateApiApplication`
- **REST v1** (`/api/v1`): `GET /me`, `customers`, `products`, `quotes`, `invoices`, `payments (+void)`, `expenses (+void)`, `vendors`, `transactions (+transfer/income/adjustment)` — paginated `?per_page` max 100
- **10 finance SaaS two-way sync**: Xero, QuickBooks Online, FreshBooks, HubSpot, Zoho Books, Wave, Sage Business Cloud, Oracle NetSuite, MYOB, Kashoo — each `Integration` row (`company_id+provider` unique, encrypted tokens, `settings` JSON, `last_sync_at`/`last_error`), `integration_sync_logs` push/pull audit, portal `Settings → Integrations` + API sync, company-isolated

### L. Dashboard, Settings, Search, Team
- Dashboard KPIs (revenue, expenses, outstanding, overdue, net), 6-month revenue vs expenses, weekly/monthly trend, expense breakdown, recent txns/outstanding
- Settings: business (logo, currency symbol/position, timezone, FY), invoice (prefix/padding/terms), tax (number/rate/inclusive), email templates + toggles + test send, SMS (`SmsManager` log/twilio/vonage/http) + test, Stripe (secret/webhook/deposit account → public `Pay now`), data export ZIP of CSVs
- Team: invite by email, 3 roles owner/accountant/staff per company (`CompanyRole`→`Permission` gates), last-owner protection, deactivate/reactivate/remove, audited
- Global search: customers/invoices/payments/expenses/transactions, grouped, permission-filtered
- Profile: name/email, password, Danger zone delete (must transfer sole ownership), legal pages
- Admin console: Blade `/admin` tenant list, suspend/reactivate, impersonate (audited), Xgenious free-software showcase

## 6. Tech Stack Grid (same `TECH_STACK` shape)

| Name | Role |
|---|---|
| Laravel 13 | PHP backend, routing, ORM, queues |
| React 19 | TypeScript + Inertia v3 SPA |
| Tailwind CSS 4 | Utility styling |
| MySQL 8 / SQLite | Prod / local |
| Spatie Permission (teams) | RBAC |
| Barryvdh DomPDF | Invoice PDFs |
| Vite 7 | Build |
| SMS `SmsManager` | log/twilio/vonage/http |
| Stripe `StripeGateway` | Checkout + webhook |

## 7. Server Requirements Grid

| Label | Value |
|---|---|
| PHP | 8.4+ (mbstring, xml, curl, zip, bcmath, intl, gd, pdo_mysql/pdo_sqlite) |
| Database | MySQL 8.0+ / MariaDB 10.4+ |
| Composer | 2.x |
| Node.js | 20+ (build) |
| Web server | Nginx / Apache |
| Queue | `php artisan queue:work --tries=3` (Supervisor) |
| Scheduler | `* * * * * php artisan schedule:run` (cron) |

## 8. Roles Grid (same `ROLES` shape)

Owner: full company + settings + team + export · Accountant: customers/products/invoices/payments/expenses/accounts/reports but no settings/team · Staff: customers/products/invoices/payments/expenses but no void/payment-void/accounts/reports/settings

## 9. FAQ (6, same shape)

Mirror HRM FAQ but accounting-specific: free? modify? multi-company? cPanel? self-hosted vs SaaS? upgrade?

## 10. Sections Order on Page (match HRM)

Hero → Stats bar → Screenshots (ScreenshotGallery) → What is it (3 paras) → 12 Modules grid → RBAC (6 roles) → Tech stack → Server Requirements (dark `#0F1112`) → BookingCTA → FAQ → Final CTA (bg image + DownloadButton). Use same Tailwind classes, `CheckIcon`, card rounding `rounded-2xl`, border `#E5E7EC`.

## 11. Assets

Screenshots: export from portal (use demo tenant): `dashboard.png` (KPIs), `invoices.png` (list with badges), `invoice-form.png`, `payments.png`, `expenses.png`, `reports.png`. Place under `public/site-images/free-software/genius-books/`.

## 12. Checklist Before PR

- [ ] `page.tsx` builds (`npm run build` in xgenious-next)
- [ ] `metadata` canonical + keywords + openGraph
- [ ] `softwareSchema` + `faqSchema` JSON-LD renders valid (Google Rich Results test)
- [ ] `DownloadButton` props: `productName="Genius Books"`, `productColor="#4f46e5"`, `licenseUuid` correct
- [ ] Add entry to `data/free-software.ts` (or `app/free-software/page.tsx` listing) — title, excerpt, href `/free-software/genius-books`, tag `Accounting`, color
- [ ] Update `sitemap.ts` / `robots.ts` if they enumerate free-software slugs
- [ ] Lighthouse 95+ performance, alt text on gallery

## 13. References Inside GeniousBooks Repo

- User Manual: `docs/user-manual.html` §5-17
- API docs: `docs/api-documentation.html` (endpoints, test cases)
- Developer guide: `docs/developer-guide.html` (architecture, ledger, tenancy)
- Models: `app/Models` (Customer, Invoice, Payment, Expense, Transaction, Integration)
- Commit history: `git log --oneline` — last 5 feat commits show pattern

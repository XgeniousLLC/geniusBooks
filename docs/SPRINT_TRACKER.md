# Xgenious Accounting — MVP Sprint & Ticket Tracker

Working plan for the Xgenious Accounting multi-tenant SaaS MVP. This document is the
single source of truth for sprint scope, tickets, dependencies, and progress.

The north star is the **Section 31 demonstration journey** (see
[Demo Path Mapping](#section-31-demo-path--ticket-mapping)): a polished, functional
end-to-end business flow that proves Xgenious can build real accounting software.
Everything else in the MVP exists to make that journey trustworthy for real,
day-one business use.

---

## 1. How to Use This Document

### Status legend

| Status | Meaning |
|---|---|
| `TODO` | Not started |
| `IN PROGRESS` | Actively being worked |
| `BLOCKED` | Waiting on a dependency or decision |
| `REVIEW` | Implemented, awaiting review/QA |
| `DONE` | Meets all acceptance criteria and passed its quality gate |

### Ticket ID scheme

`S{sprint}-{nn}` — e.g. `S0-01` (Sprint 0, ticket 01). Sprint 0 is numbering/tenancy
foundation; later sprints build domain features.

### Priority

| Priority | Meaning |
|---|---|
| `P0` | Demo blocker / launch blocker — cannot ship without it |
| `P1` | Required MVP — needed for a genuinely usable product |
| `P2` | Polish / nice-to-have within MVP |

### Estimate

`S` (≤ ~half day) · `M` (≈ 1 day) · `L` (≈ 2–3 days). Estimates are sizing hints,
not commitments.

### Ticket format

```
### S{sprint}-{nn} — <Title>
- Type: Feature | Chore | Infra | Bug · Priority: P0|P1|P2 · Est: S|M|L · Status: TODO
- Depends on: <ticket ids or "—">
- Scope: <what is built, and the layer/module that owns it>
- Acceptance:
  - [ ] <observable, testable condition>
  - [ ] <invariant preserved / trust boundary respected>
```

Tickets are markdown checkboxes so progress is greppable
(`rg "\[ \]"` for open, `rg "\[x\]"` for done).

---

## 2. Locked Architecture Decisions

These are settled and shape every ticket below.

| Decision | Choice | Rationale |
|---|---|---|
| UI surface | **React 19 + Inertia + TypeScript portal** (`resources/js`), `web` guard | Matches the spec's React/Next direction and reuses the existing scaffold |
| Platform operator surface | Existing **Blade admin panel** (`admin` guard) — repurposed as the platform super-admin console | Not a tenant surface; used to manage tenants |
| Accounting engine | **Single-entry transaction ledger** linked to source documents and accounts; balances derived | Satisfies "proper financial records" without the full double-entry journal UI the MVP excludes |
| Multi-tenancy | New `companies` table + `company_user` membership pivot + `company_id` on every domain table + global scope via a `BelongsToCompany` trait | Isolated workspaces; supports accountants managing multiple businesses |
| RBAC | **spatie/laravel-permission** using its **teams** feature, team = company | Roles Owner / Accountant / Staff, assignable per company |
| Currency | **Locked to one currency per company** at launch | Avoids FX complexity; cross-currency rejected with a clear error |
| Money storage | Integer minor units (e.g. cents) + explicit rounding rules | Avoids floating-point drift in financial records |
| Chart of accounts vs bank accounts | Distinct: `ledger_accounts` (Assets/Liabilities/Equity/Revenue/Expenses) vs `bank_accounts` (Cash/Bank) | The spec uses "accounts" for both; they must not be conflated |
| PDF | `barryvdh/laravel-dompdf` | Mature, pure-PHP, no external binary |
| Email | **Platform-managed sending** (one verified domain), per-company display name/reply-to | Simple, deliverable at launch |
| Monetization | **None — free SaaS** for the audience | No plans/trial/billing layer in scope |
| File storage | Outside web root, MIME + size validated, authorized download endpoints | Per scaffold constraints |
| Data migration | CSV import + opening balances supported at launch | Existing businesses can adopt the product |

### Environment baseline (verified)

- `MAIL_MAILER=log` in `.env.example` — must be switched to a real transport (S0-12).
- `deploy/install.sh` sets up a queue worker and nightly DB backup but **no
  `schedule:run` cron** — required for reminders/overdue status (S0-13).
- `FILESYSTEM_DISK=local`, S3 variables empty — S3-compatible storage is not wired.
- Email verification is disabled (`MustVerifyEmail` commented out) — enabled in S0-14.
- No error tracking / failed-job alerting — added in S0-13/S0-14.

---

## 3. Domain Model Map

All tenant tables carry `company_id` and are filtered by the `BelongsToCompany` global
scope. Reference/seed tables may be global.

| Module | Tables | Key relations |
|---|---|---|
| Platform | `companies`, `company_user`, `roles`, `permissions`, `model_has_roles`, `audit_logs` | Company hasMany members; Company hasMany domain records |
| Identity | `users` (tenant members), `invitations` | User belongsToMany Companies (role on pivot) |
| Customers | `customers` | Customer belongsTo Company; hasMany Invoices, Payments |
| Catalog | `products` | Product belongsTo Company; belongsToMany/morph TaxRate |
| Sales | `invoices`, `invoice_items`, `credit_notes` | Invoice belongsTo Customer; hasMany Items, Payments |
| Payments | `payments`, `payment_allocations` | Payment belongsTo Customer, BankAccount; hasMany Allocations → Invoice |
| Expenses | `expenses`, `expense_categories`, `vendors` | Expense belongsTo Category, Vendor, BankAccount |
| Accounting | `ledger_accounts`, `transactions`, `bank_accounts` | Transaction belongsTo LedgerAccount, BankAccount; morphs to source document |
| Settings | `company_settings`, `tax_rates`, `email_templates` | Scoped per Company |

Cross-cutting: every financial posting flows through the transaction/ledger posting
service (S7-03) so balances are always derived, never mutated directly.

---

## 4. Sprint Overview

| Sprint | Goal | Tickets | P0 | Status |
|---|---|---|---|---|
| S0 | Foundation & tenant platform | 14 | 12 | DONE |
| S1 | Team, roles & access | 7 | 5 | DONE |
| S2 | Customers, products & migration | 8 | 4 | DONE |
| S3 | Invoices core | 10 | 8 | DONE |
| S4 | Invoice delivery & documents | 8 | 4 | DONE |
| S5 | Payments, accounts & credit | 9 | 5 | DONE |
| S6 | Expenses, vendors & categories | 7 | 2 | DONE |
| S7 | Transactions & chart of accounts | 7 | 3 | DONE |
| S8 | Reports | 9 | 5 | DONE |
| S9 | Dashboard & notifications | 6 | 3 | DONE |
| S10 | Settings, search, polish & launch | 8 | 3 | DONE |
| **Total** | | **93** | **54** | |

---

## Sprint 0 — Foundation & Tenant Platform

**Goal:** Stand up tenancy, identity, money/date conventions, document numbering, the
app shell, audit/void safety, and the operational substrate (mail, scheduler, jobs,
logs) that every later module depends on.

---

### S0-01 — Install & configure core dependencies
- Type: Chore · Priority: P0 · Est: M · Status: DONE
- Depends on: —
- Scope: Add `spatie/laravel-permission` (teams enabled), `barryvdh/laravel-dompdf`,
  and the chosen money/decimal helper to `composer.json`; publish and configure
  permission tables.
- Acceptance:
  - [ ] Packages installed and pinned in `composer.lock`
  - [ ] Permission migration published with teams/company column
  - [ ] `php artisan test` still green after install

### S0-02 — `companies` table, model & settings columns
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-01
- Scope: Migration + `Company` model holding name, logo, email, phone, address,
  country, currency, financial year, tax defaults, invoice prefix/numbering,
  default payment terms.
- Acceptance:
  - [ ] Migration runs forward and rolls back cleanly
  - [ ] `Company` factory produces valid records
  - [ ] Currency + financial-year fields are required and validated

### S0-03 — Membership pivot, current-company resolver & session context
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-02
- Scope: `company_user` pivot (with role), a middleware/service that resolves the
  current company from session, and a helper (`currentCompany()`) used across layers.
- Acceptance:
  - [ ] Authenticated request resolves exactly one current company
  - [ ] Missing membership yields a clear redirect/403, never another company's data
  - [ ] Current company is shared to Inertia props

### S0-04 — Company switcher UI
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-03
- Scope: Portal control to switch between companies a user belongs to (supports
  accountants managing multiple businesses); switching updates session + reloads.
- Acceptance:
  - [ ] User with N memberships can switch and sees only that company's data
  - [ ] Switch is tenant-safe (no stale props from the previous company)
  - [ ] Single-membership users see no switcher noise

### S0-05 — `BelongsToCompany` trait + global scope
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-03
- Scope: Reusable trait applying a global `company_id` scope, auto-filling
  `company_id` on create, and guarding against cross-tenant assignment.
- Acceptance:
  - [ ] All tenant models use the trait
  - [ ] A query in company A never returns company B rows
  - [ ] Creating a record without explicit company uses the current company

### S0-06 — Company onboarding wizard
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-02, S0-03
- Scope: Post-registration wizard: business info, currency, financial year, tax
  defaults, invoice prefix/numbering, payment terms, and first bank account.
- Acceptance:
  - [ ] A new user is guided through setup before reaching the dashboard
  - [ ] Completed setup produces a valid Company + first bank account
  - [ ] Wizard is resumable; validation blocks invalid money/tax config

### S0-07 — Base React/Inertia app shell & sidebar navigation
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-03
- Scope: Layout, sidebar per Section 23 (Dashboard / Sales / Expenses / Accounting /
  Reports / Settings), header, company switch entry point.
- Acceptance:
  - [ ] Navigation renders the Section 23 hierarchy
  - [ ] Active-route state, responsive collapse, and keyboard focus are correct
  - [ ] Links are permission-aware (hidden when the role lacks access)

### S0-08 — Money & rounding conventions
- Type: Chore · Priority: P0 · Est: M · Status: DONE
- Depends on: —
- Scope: Integer-minor-unit storage, a money value object/casts, currency formatting
  helpers, and documented rounding rules for tax/discount/totals.
- Acceptance:
  - [ ] Amounts stored as integers; no PHP float math for money
  - [ ] Formatting honors company currency and locale
  - [ ] Rounding rules are covered by unit tests

### S0-09 — Date, timezone, number formatting & financial-year boundaries
- Type: Chore · Priority: P1 · Est: M · Status: DONE
- Depends on: S0-02
- Scope: Per-company timezone and date-format handling; financial-year boundary logic
  used by reports.
- Acceptance:
  - [ ] Dates rendered consistently in company timezone
  - [ ] Financial-year boundaries computed from company settings
  - [ ] Boundary logic unit-tested

### S0-10 — Audit log + void/soft-delete convention
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-05
- Scope: `audit_logs` table + observer recording who/when/what; policy that financial
  records are voided (with reason) rather than hard-deleted.
- Acceptance:
  - [ ] Create/update/void on financial records is logged with actor + timestamp
  - [ ] No endpoint allows hard-deleting sent invoices, payments, or expenses
  - [ ] Audit entries are tenant-scoped and immutable

### S0-11 — Atomic per-company document numbering service
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-02
- Scope: Transactional, locked sequence generator for invoice (and future) numbers
  honoring prefix/numbering; defines gap/reuse policy.
- Acceptance:
  - [ ] Concurrent creation cannot produce duplicate numbers
  - [ ] Numbers respect company prefix and configured padding
  - [ ] Concurrency behavior covered by a test

### S0-12 — Mail transport (platform-managed sending)
- Type: Infra · Priority: P0 · Est: M · Status: DONE
- Depends on: —
- Scope: Configure real mail transport and a verified sending domain; per-company
  display name/reply-to; replace `MAIL_MAILER=log`; test-send command.
- Acceptance:
  - [ ] A queued test email is actually delivered
  - [ ] Sender identity comes from platform config, reply-to from company settings
  - [ ] SPF/DKIM guidance documented in deploy docs

### S0-13 — Scheduler, queue reliability & failed-job handling
- Type: Infra · Priority: P0 · Est: M · Status: DONE
- Depends on: —
- Scope: Add `schedule:run` cron to `deploy/install.sh`, define schedules for
  reminders/overdue/recurring tasks, ensure retries + `failed_jobs` visibility.
- Acceptance:
  - [ ] Deploy script installs the scheduler cron
  - [ ] Failed jobs are recorded and surfaced
  - [ ] A scheduled command runs end-to-end in a staging check

### S0-14 — Observability, logging & email verification
- Type: Infra · Priority: P1 · Est: M · Status: DONE
- Depends on: —
- Scope: Structured logging, error-tracking hook, health checks, and enabling email
  verification on registration.
- Acceptance:
  - [ ] Errors are captured with context (not silent failures)
  - [ ] `/up` health check and log channel are configured for production
  - [ ] Unverified users are gated from transactional actions per policy

**Sprint 0 exit criteria:** a new user can register, verify email, create a company
with currency/tax/numbering, reach an empty permission-aware shell, and the platform
can send mail and run scheduled jobs — all tenant-isolated and audited.

### Sprint 0 — Delivery notes

- **Shipped:** `companies`, `company_user`, `document_sequences`, `audit_logs` and
  spatie permission (teams) migrations; `Company`, `DocumentSequence`, `AuditLog`
  models; `BelongsToCompany`, `Auditable`, `Voidable` traits; `CompanyContext`,
  `Money`, `FinancialYear` support; `AuditLogger`, `DocumentNumberService`,
  `CompanyProvisioningService`; `ResolveCurrentCompany` middleware + Inertia company
  props; onboarding wizard; company switcher; Section 23 app shell; email
  verification; `mail:test` command; scheduler cron in `deploy/install.sh`; request
  log context.
- **Test coverage:** 34 new tests (Money, FinancialYear, tenancy isolation, document
  numbering, audit/void, onboarding, switcher, email verification, mail). Full suite:
  128 passing; the 32 failures are the scaffold's known-broken admin/SEO tests
  (pre-existing, unchanged).
- **Deviation:** the membership pivot (`company_user`) holds membership flags only;
  roles live in spatie's `model_has_roles` scoped by `team_id = company_id` (single
  source of truth). Roles seeded on company creation: owner, accountant, staff.
- **Deferred to later sprints (by design):** role/permission enforcement (S1),
  domain models (S2+). Nav items without routes render as non-interactive "Soon".
- **Ops note:** outbound mail still defaults to the `log` mailer locally; set SMTP
  credentials in production and verify with `php artisan mail:test`.

---

---

## Sprint 1 — Team, Roles & Access

**Goal:** Make the workspace collaborative and safely isolated: invites, roles,
policies, isolation tests, and the platform operator console.

---

### S1-01 — Invitation + accept flow
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-12
- Scope: Invite a teammate by email with a signed, expiring token; acceptance sets
  password and creates the membership.
- Acceptance:
  - [ ] Invite email delivers with a working accept link
  - [ ] Expired/reused tokens are rejected
  - [ ] Accepted user lands in the correct company with the invited role

### S1-02 — Role assignment with spatie teams scoping
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-01, S1-01
- Scope: Wire Owner / Accountant / Staff roles as spatie teams (team = company);
  assign/update roles per membership.
- Acceptance:
  - [ ] A user's roles are scoped to the current company only
  - [ ] Role changes take effect without leaking across companies
  - [ ] Seed data defines the three roles

### S1-03 — Deactivate/remove member + last-owner protection
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S1-02
- Scope: Remove or deactivate a member; block removing/demoting the final owner.
- Acceptance:
  - [ ] Removed member immediately loses access
  - [ ] Last owner cannot be removed or demoted
  - [ ] Actions are audited

### S1-04 — Policies & gates enforced by role
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S1-02
- Scope: Policies/abilities for customers, invoices, payments, expenses,
  transactions, accounts, reports, settings, users.
- Acceptance:
  - [ ] Staff/Accountant/Owner boundaries match the spec's permission intent
  - [ ] UI hides disallowed actions and server rejects them (defense in depth)
  - [ ] Authorization covered by feature tests

### S1-05 — Tenant-isolation test harness
- Type: Chore · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-05
- Scope: Reusable tests asserting company A cannot read/write/delete company B
  records across every tenant model.
- Acceptance:
  - [ ] Isolation tests exist for each module as it lands
  - [ ] Direct-ID access to another tenant returns 404/403
  - [ ] Harness runs in CI

### S1-06 — Platform console: tenant list & search
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S0-02
- Scope: Blade admin page listing companies with search, status, owner, and counts.
- Acceptance:
  - [ ] Operators can find a tenant and open its overview
  - [ ] No tenant financial detail is exposed beyond stated operational needs
  - [ ] Access restricted to platform admins

### S1-07 — Platform console: suspend/reactivate + audited impersonation + usage
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S1-06
- Scope: Suspend/reactivate tenants, audited support impersonation, and usage metrics.
- Acceptance:
  - [ ] Suspended tenants are blocked with a clear message
  - [ ] Impersonation is logged (who, whom, when) and visibly indicated
  - [ ] Usage view shows seats, invoices, and storage at a glance

**Sprint 1 exit criteria:** a company owner can invite and manage a team, roles are
enforced server-side, cross-tenant isolation is proven, and operators can manage
tenants safely.

### Sprint 1 — Delivery notes

- **Shipped:** `CompanyRole` + `Permission` enums with a role→ability matrix; gates
  registered per ability; `CompanyPolicy` + `InvitationPolicy`; `invitations` table,
  `Invitation` model/factory, `InvitationMail`; membership management
  (role sync, deactivate/reactivate, remove) with last-owner protection; invitation
  invite/revoke/accept flow (new and existing users); platform console tenant list,
  detail, suspend/reactivate, audited impersonation + stop; suspended-workspace page;
  `Settings/Users` portal page; tenant-isolation harness
  (`tests/Concerns/AssertsTenantIsolation.php`).
- **Test coverage:** 28 new tests (membership guards, invitations, role gates,
  platform console, cross-tenant denial). Full suite: 156 passing; the 32 failures
  remain the pre-existing broken scaffold admin/SEO tests.
- **Bug found and fixed:** switching the active company (permission team) left stale
  cached role relations on the user; `ResolveCurrentCompany` now invalidates the
  `roles`/`permissions` relations after resolving the team. This is required for
  correct authorization when a user switches between businesses.
- **Deviation:** S1-04 domain policies (customers/invoices/…) are deferred until
  those models exist (S2+). This sprint delivers the authorization infrastructure:
  gates, the role matrix, and policies for the currently existing tenant models.
  Visibility of the "Users & Roles" nav item is limited to owners; all endpoints are
  enforced server-side regardless of UI.

---

---

## Sprint 2 — Customers, Products & Migration

**Goal:** Deliver the customer and catalog foundation, plus the migration paths that
let real businesses onboard.

---

### S2-01 — Customer schema, model & policy
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-05, S1-04
- Scope: Customer fields (name, company, email, phone, billing/shipping address,
  tax ID, currency, payment terms, notes) + model + policy.
- Acceptance:
  - [ ] Migration forward/rollback clean
  - [ ] All required fields validated; currency locked to company currency
  - [ ] Policy restricts by role

### S2-02 — Customer CRUD + list with search/filter/pagination
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S2-01, S2-08
- Scope: Create/edit/show/archive customers; list with search, filters, pagination.
- Acceptance:
  - [ ] Full CRUD works and is tenant-scoped
  - [ ] Search matches name/email/company; filters work
  - [ ] Form validation errors are surfaced inline

### S2-03 — Customer profile (invoices, payments, balance)
- Type: Feature · Priority: P0 · Est: L · Status: DONE (completed in Sprint 5)
- Depends on: S2-01, S3-01, S5-03
- Scope: Profile view with total invoiced, paid, outstanding, invoices, payments, and
  transaction history; entry point to customer statement.
- Acceptance:
  - [ ] Totals reconcile with invoices/payments
  - [ ] Outstanding = invoiced − paid (including credits)
  - [ ] Links resolve to correct filtered lists

### S2-04 — Product/Service schema + CRUD
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S2-08
- Scope: Name, SKU, description, type (product/service), unit price, tax, category,
  status + CRUD.
- Acceptance:
  - [ ] Price/tax stored with correct money/rate conventions
  - [ ] Active/inactive controls invoice picker visibility
  - [ ] Validation prevents negative prices

### S2-05 — Product/Service list, search & filter
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S2-04
- Scope: List with search (name/SKU), type/category/status filters, pagination.
- Acceptance:
  - [ ] Filters compose correctly
  - [ ] Empty/loading states handled
  - [ ] Tenant-scoped results only

### S2-06 — CSV import: customers & products
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S2-02, S2-04
- Scope: Upload, column mapping, validation preview, row-level errors, transactional
  import; reuse for both customers and products.
- Acceptance:
  - [ ] Invalid rows are reported without corrupting partial imports
  - [ ] Import is atomic (all-or-nothing) or clearly reports applied rows
  - [ ] Duplicate handling policy is explicit

### S2-07 — Opening balances: customers & accounts
- Type: Feature · Priority: P1 · Est: L · Status: DONE (completed in Sprint 8)
- Depends on: S2-01, S5-01, S7-03
- Scope: Record opening balances for customer balances and bank/ledger accounts so
  statements and reports start from the correct position.
- Acceptance:
  - [ ] Opening balances post as ledger entries, not silent field mutations
  - [ ] Customer statement reflects opening balance
  - [ ] P&L/receivables exclude opening balances correctly

### S2-08 — Shared table primitives
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-07
- Scope: Reusable list component: pagination, sorting, filters, empty/loading/error
  states, and bulk actions.
- Acceptance:
  - [ ] Adopted by customer/product/other lists consistently
  - [ ] Accessible (labels, focus, announcements)
  - [ ] Query params preserved in URL

**Sprint 2 exit criteria:** customers and catalog are fully manageable, importable,
and balance-aware, with reusable list UX.

### Sprint 2 — Delivery notes

- **Shipped:** `customers` + `products` tables, models, factories, policies;
  `ListQuery` (search/sort/pagination/filters) and a reusable React `DataTable`
  (sortable headers, pagination, empty state); customer CRUD + list, product CRUD +
  list; CSV import for customers and products with all-or-nothing validation,
  duplicate skipping, row-level error reporting and downloadable templates; nav
  entries for Customers and Products & Services.
- **Test coverage:** 21 new tests (customer CRUD/filters, product pricing/SKU
  uniqueness, CSV import/templates, cross-tenant customer & product denial). Full
  suite: 175 passing; the 32 failures remain the pre-existing broken scaffold
  admin/SEO tests.
- **Decisions:** unit prices are stored as integer minor units; customer currency is
  locked to the company currency (the form shows it read-only); SKU is unique per
  company; archiving uses soft deletes. Imports validate the whole file first and
  write nothing if any row is invalid.
- **Blocked (dependency, not completed):**
  - **S2-03 Customer profile** — needs invoices (S3-01) and payments (S5-03). The
    customer show page exists with contact/account details and a clearly-labelled
    empty financial-history section; invoiced/paid/outstanding aggregation lands in
    Sprint 5.
  - **S2-07 Opening balances** — must post as ledger entries (S7-03) against
    accounts (S5-01); deferred to Sprint 7 to avoid a silent field mutation.

---

---

## Sprint 3 — Invoices Core

**Goal:** Build the invoice engine: line items, discounts, taxes, totals, numbering,
and the status lifecycle — the MVP's central module.

---

### S3-01 — Invoice & invoice_items schema + models
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-11, S2-01, S2-04
- Scope: Invoice (customer, number, dates, terms, notes, status) + line items
  (product, qty, unit price, discount, tax, amount).
- Acceptance:
  - [ ] Forward/rollback clean; money columns integer
  - [ ] Line items cascade correctly on draft delete
  - [ ] Invoice number assigned via S0-11 service

### S3-02 — Invoice create/edit UI with line items
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S3-01
- Scope: Form with customer selection, product picker, quantity, rate, tax, discount,
  notes; live totals preview.
- Acceptance:
  - [ ] Adding/removing lines updates totals correctly
  - [ ] Product selection pre-fills price/tax and remains editable
  - [ ] Validation blocks empty/invalid invoices

### S3-03 — Server-side totals calculator
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-08, S3-01
- Scope: Authoritative recomputation of subtotal, discount, tax, and total; UI preview
  is never trusted.
- Acceptance:
  - [ ] Totals match across UI, PDF, and database
  - [ ] Rounding matches S0-08 rules
  - [ ] Unit tests cover inclusive/exclusive and discount combinations

### S3-04 — Discount model (line-level & invoice-level)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S3-03
- Scope: Percent or fixed discounts at line and/or invoice level; documented
  interaction order with tax.
- Acceptance:
  - [ ] Discount application order is deterministic and documented
  - [ ] Invoice total always equals recomputed value
  - [ ] Edge cases (100% discount, discount > subtotal) validated

### S3-05 — Tax model (per-line rates, inclusive/exclusive)
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-02, S3-03
- Scope: Configurable tax rates, inclusive/exclusive handling, company tax
  registration number; no hard-coded country rules.
- Acceptance:
  - [ ] Inclusive and exclusive pricing both compute correctly
  - [ ] Tax summary data is captured per line
  - [ ] No jurisdiction rules are hard-coded

### S3-06 — Invoice status lifecycle + derived status
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S3-01
- Scope: Draft → Sent → Viewed → Partially Paid → Paid, plus Overdue and Cancelled;
  status derived from payments/dates where possible.
- Acceptance:
  - [ ] Payment/date changes correctly transition status
  - [ ] Overdue is derived from due date + balance
  - [ ] Status transitions are audit-logged

### S3-07 — Invoice list + filters + status badges
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S2-08, S3-06
- Scope: List with filters (status, customer, date, amount) and clear status badges.
- Acceptance:
  - [ ] Filters compose and persist in URL
  - [ ] Badges map unambiguously to status
  - [ ] Tenant-scoped

### S3-08 — Invoice detail/show page
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S3-06
- Scope: Full invoice view with line items, totals, payments applied, balance due, and
  action entry points.
- Acceptance:
  - [ ] Shows paid + balance due derived from payments
  - [ ] Reflects status accurately
  - [ ] Action buttons respect permissions

### S3-09 — Duplicate invoice
- Type: Feature · Priority: P1 · Est: S · Status: DONE
- Depends on: S3-02
- Scope: Duplicate an invoice into a new draft with a fresh number.
- Acceptance:
  - [ ] New draft copies lines but not payments/status
  - [ ] New number assigned via S0-11
  - [ ] Original is untouched

### S3-10 — Cancel/void invoice + sent-invoice edit rules
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-10, S3-06
- Scope: Cancel/void with reason; draft editable, sent/paid immutable (adjust via
  credit note).
- Acceptance:
  - [ ] Voided invoices preserve history and are excluded from AR/revenue
  - [ ] Editing a sent invoice is blocked with a clear path to a credit note
  - [ ] Void is audited

**Sprint 3 exit criteria:** invoices can be created, calculated, numbered, listed, and
lifecycle-managed with correct, auditable totals.

### Sprint 3 — Delivery notes

- **Shipped:** `invoices` + `invoice_items` tables/models/factories;
  `InvoiceStatus` enum with stored + derived states; `InvoiceCalculator` (line and
  invoice discounts, per-line tax, inclusive/exclusive, remainder-safe allocation)
  as the authoritative totals engine; `InvoiceService` (create/update/duplicate);
  `InvoicePolicy`; full invoice CRUD, list with search/status/customer filters and
  badges, detail page, mark-as-sent, cancel, duplicate, draft-only delete;
  client-side totals mirror for live preview; nav entry for Invoices.
- **Test coverage:** 22 new tests (calculator edge cases incl. allocation remainder
  and inclusive tax; invoice create/update/validation, sent-invoice immutability,
  draft delete, cancel, duplicate, filters, detail formatting, cross-tenant denial).
  Full suite: 193 passing; the 32 failures remain the pre-existing broken scaffold
  admin/SEO tests.
- **Decisions:** totals are recomputed server-side and stored as snapshots; the UI
  preview is never trusted. Invoice numbers allocate atomically on creation (gaps
  permitted). Drafts are deletable; sent invoices are immutable and cancelled, never
  deleted (enforced in the policy and the model). Discounts/clamping rules are
  documented in `InvoiceCalculator`.
- **Activation deferred to Sprint 5:** `Partially paid` / `Paid` states and
  `amount_paid` transitions are implemented in the derivation logic but become
  reachable once payments exist (S5-03). The overdue derivation is live now.

---

---

## Sprint 4 — Invoice Delivery & Documents

**Goal:** Make invoices sendable and payable: professional PDFs, email delivery,
secure public viewing, and reminders.

---

### S4-01 — Invoice PDF template
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S3-03
- Scope: Branded PDF with logo, company address, tax ID, invoice/due dates, bill-to,
  line items, subtotal/tax/total, paid/balance, footer, and **bank/payment
  instructions**.
- Acceptance:
  - [ ] PDF totals match the invoice exactly
  - [ ] Payment instructions are present and configurable
  - [ ] Layout is clean across typical line-item counts

### S4-02 — PDF download & print
- Type: Feature · Priority: P0 · Est: S · Status: DONE
- Depends on: S4-01
- Scope: Download endpoint (authorized, tenant-scoped) and print-styled view.
- Acceptance:
  - [ ] Unauthorized/cross-tenant download is blocked
  - [ ] Filename includes invoice number
  - [ ] Print view hides app chrome

### S4-03 — Invoice email send
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-12, S4-01
- Scope: Queued email with invoice attached, template variables, platform sender +
  company reply-to.
- Acceptance:
  - [ ] Sending queues and delivers; failures are retried/logged
  - [ ] Template variables render correctly
  - [ ] Successful send sets status Sent

### S4-04 — Secure public invoice link
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S4-01
- Scope: Signed, expiring URL allowing authless invoice view/download.
- Acceptance:
  - [ ] Tampered/expired links are rejected
  - [ ] Public view exposes only that invoice
  - [ ] No tenant data leaks via the public route

### S4-05 — "Viewed" tracking
- Type: Feature · Priority: P1 · Est: S · Status: DONE
- Depends on: S4-04, S3-06
- Scope: Record first view + timestamp when the public link is opened.
- Acceptance:
  - [ ] Viewing transitions status to Viewed once
  - [ ] Only genuine views count (not prefetch)
  - [ ] Event is audited

### S4-06 — Scheduled reminders (due soon / overdue)
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S0-13, S3-06, S4-03
- Scope: Configurable offsets before/after due date; timezone-aware; respects company
  and per-customer settings.
- Acceptance:
  - [ ] Reminders fire on schedule without duplicates
  - [ ] Paid/voided invoices are skipped
  - [ ] Recipients and offsets are configurable

### S4-07 — Email settings (sender, reply-to, templates)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S0-12
- Scope: Company-level sender name, reply-to, and editable email templates.
- Acceptance:
  - [ ] Settings persist per company and apply to outbound mail
  - [ ] Template preview renders with sample variables
  - [ ] Invalid addresses are rejected

### S4-08 — Test-send & delivery troubleshooting
- Type: Feature · Priority: P2 · Est: S · Status: DONE
- Depends on: S4-07
- Scope: "Send test email" action + minimal delivery log view.
- Acceptance:
  - [ ] Operators can confirm deliverability from settings
  - [ ] Recent sends status is visible
  - [ ] Errors are human-readable

**Sprint 4 exit criteria:** an invoice can be sent as a professional, payable PDF and
tracked through viewing, with working reminders.

### Sprint 4 — Delivery notes

- **Shipped:** `InvoicePdfService` + `documents/invoice` PDF template (logo, address,
  tax ID, payment instructions, footer, totals, paid/balance); `EmailTemplateService`
  with per-company templates and variable rendering; `InvoiceDeliveryService` (render,
  attach PDF, queue email, log delivery); `InvoiceMail`; public signed-URL invoice view
  with first-view tracking and public PDF; `invoices:send-reminders` command scheduled
  daily with due/overdue dedupe columns; `email_templates` + `email_logs` tables;
  company delivery settings (`payment_instructions`, `invoice_footer`, sender,
  reminders); `Settings/Email` portal page (sender, document details, reminders,
  template editor, test-send, recent sends); `Invoices/Public` page; email + PDF
  actions on the invoice detail page; nav entry for Email Settings.
- **Test coverage:** 22 new tests (PDF download, email queued/logged/status, missing
  email guard, signed public view + view tracking, unsigned rejection, public PDF,
  reminder due/overdue/window/toggle/paid, email settings CRUD + test-send,
  cross-tenant PDF/email denial). Full suite: 209 passing; the 32 failures remain the
  pre-existing broken scaffold admin/SEO tests.
- **Decisions:** public invoice access uses Laravel temporary signed URLs (30 days);
  the first authenticated public view advances Sent → Viewed; reminders are idempotent
  per invoice via `due_reminder_sent_at` / `overdue_reminder_sent_at`; email templates
  fall back to built-in defaults when a company has not customised them.
- **Note:** PDF/email rendering runs synchronously before queueing so the PDF is
  attached to the queued mail; platform mailer credentials (`MAIL_*`) still need to be
  configured in production for real delivery.

---

---

## Sprint 5 — Payments, Accounts & Credit

**Goal:** Record and reconcile money in, including partial payments, overpayment
credit, allocation across invoices, and credit notes/refunds.

---

### S5-01 — Bank/cash account schema + CRUD
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-06
- Scope: Accounts (name, type, opening balance) + CRUD.
- Acceptance:
  - [ ] Opening balance posts via the ledger (S7-03), not a silent field
  - [ ] Accounts are tenant-scoped
  - [ ] Type drives available payment methods

### S5-02 — Account transaction history + running balance
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S5-01, S7-03
- Scope: Per-account transaction list with running balance.
- Acceptance:
  - [ ] Running balance reconciles to opening + postings
  - [ ] Filters by date/type work
  - [ ] No direct balance mutation path exists

### S5-03 — Payment schema + record payment
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S3-06, S5-01
- Scope: Payment (invoice, customer, date, amount, method, account, reference, notes)
  + recording UI.
- Acceptance:
  - [ ] Payment cannot exceed invoice balance without explicit overpayment path
  - [ ] Recording is transactional and posts to the ledger
  - [ ] Invoice status/balance update immediately

### S5-04 — Partial payment + idempotent posting
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S5-03, S7-03
- Scope: Partial payment handling and duplicate-submit protection (idempotency key /
  locking).
- Acceptance:
  - [ ] Partial payment sets Partially Paid and correct remaining balance
  - [ ] Double-submit cannot create duplicate payments
  - [ ] Concurrent posting cannot corrupt balances

### S5-05 — Payment methods
- Type: Feature · Priority: P0 · Est: S · Status: DONE
- Depends on: S5-03
- Scope: Cash, Bank Transfer, Credit/Debit Card, Other.
- Acceptance:
  - [ ] Method selectable and stored correctly
  - [ ] Method available per account type rules
  - [ ] Extensible for later gateways without schema break

### S5-06 — Overpayment → unapplied credit
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S5-04
- Scope: Overpayments become customer credit balance, visible and applicable.
- Acceptance:
  - [ ] Overpayment is recorded as credit, not negative invoice
  - [ ] Customer credit balance is visible on the profile
  - [ ] Credit can be applied later without double counting

### S5-07 — Payment allocation across multiple invoices
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S5-06
- Scope: `payment_allocations` allowing one payment to settle several invoices.
- Acceptance:
  - [ ] Allocation sums never exceed the payment amount
  - [ ] Affected invoices update status correctly
  - [ ] Reallocation/removal keeps balances consistent

### S5-08 — Credit notes & refunds
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S5-04, S0-10
- Scope: Issue credit notes against invoices, apply to balance or refund via an
  account, with correct ledger impact.
- Acceptance:
  - [ ] Credit note reduces balance and posts correctly
  - [ ] Refund moves money out of the chosen account
  - [ ] Both are voidable and audited

### S5-09 — Payment list + filters + receipt email
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S5-03, S2-08
- Scope: Payments list with filters, and an optional payment-received receipt email.
- Acceptance:
  - [ ] Filters by customer/account/date/method work
  - [ ] Receipt email sends with correct amount/reference
  - [ ] Tenant-scoped

**Sprint 5 exit criteria:** money-in is fully captured, allocated, and reconciled, with
credits and refunds handled correctly.

### Sprint 5 — Delivery notes

- **Shipped:** `bank_accounts`, `transactions`, `payments`, `payment_allocations`,
  `credit_notes` tables/models/factories; `TransactionType`/`TransactionDirection`/
  `PaymentMethod` enums; **`LedgerPostingService`** (the single money-movement entry
  point, with transfers and reversals); `PaymentService` (idempotent recording,
  allocations, overpayment credit, void/reversal, invoice recompute);
  `CreditNoteService` (issue + optional refund); policies; bank account CRUD with
  running-balance history; payment record/list/detail/void with multi-invoice
  allocation and receipts; credit notes list + issue from an invoice; customer
  profile financials (invoiced/paid/credited/outstanding/credit) and recent
  invoices/payments; nav for Accounts and Payments.
- **Test coverage:** 24 new tests (ledger balance/transfer/reversal, account CRUD +
  history, payment partial/full/overpay/idempotency/multi-allocation/void/validation/
  receipt, credit note issue/refund/limit/permissions, cross-tenant accounts &
  payments) plus the customer profile summary test. Full suite: 229 passing; the 32
  failures remain the pre-existing broken scaffold admin/SEO tests.
- **Ordering decision:** `S7-03` (transaction ledger + posting service) was delivered
  early because payments cannot satisfy the DoD otherwise. Sprint 7 will build the
  remaining ledger work (chart of accounts, transaction list UI, transfers,
  adjustments, direct income) on top of it. `S5-02` therefore is complete.
- **Carry-over completed:** `S2-03` customer profile is done (it depended on S3-01 +
  S5-03). `S2-07` opening balances remains deferred to the customer-statement work in
  Sprint 8.
- **Decisions:** invoice `amount_paid`/`credit_total` are recomputed from
  allocations/credit notes; invoice balance and paid/partial/overdue status derive
  from them. Payment submissions carry a client-generated idempotency key (unique per
  company). Overpayments become unapplied customer credit. Account balances are always
  derived from the ledger, never mutated.

---

---

## Sprint 6 — Expenses, Vendors & Categories

**Goal:** Capture money-out with categories, vendors, attachments, recurrence, and
ledger posting.

---

### S6-01 — Expense categories (defaults + custom)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S0-02
- Scope: Seed the default category list; allow custom categories per company.
- Acceptance:
  - [ ] Defaults seeded on company creation
  - [ ] Custom categories are tenant-scoped
  - [ ] In-use categories cannot be hard-deleted

### S6-02 — Vendor schema + CRUD
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S2-08
- Scope: Vendors with basic contact/tax fields + CRUD + list.
- Acceptance:
  - [ ] Vendors link to expenses
  - [ ] List supports search/filter
  - [ ] Tenant-scoped

### S6-03 — Expense schema + CRUD
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S6-01, S6-02, S5-01
- Scope: Expense (date, category, vendor, amount, tax, payment account, description,
  recurring flag, notes) + CRUD.
- Acceptance:
  - [ ] Expense reduces the chosen account balance via posting
  - [ ] Validation blocks invalid amounts/dates
  - [ ] Create/edit are audited

### S6-04 — Expense attachment (safe storage & download)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S6-03
- Scope: Receipt upload with MIME/size validation, stored outside web root, authorized
  download.
- Acceptance:
  - [ ] Disallowed file types/sizes are rejected
  - [ ] Files are not web-accessible directly
  - [ ] Only authorized company users can download

### S6-05 — Recurring expense flag + scheduler
- Type: Feature · Priority: P2 · Est: M · Status: DONE
- Depends on: S0-13, S6-03
- Scope: Mark expenses recurring; scheduler generates the next occurrence.
- Acceptance:
  - [ ] Recurring creation is idempotent per period
  - [ ] Generated expenses are auditable and editable
  - [ ] Schedule respects company timezone

### S6-06 — Expense → ledger posting
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S6-03, S7-03
- Scope: Post each expense as a transaction against a ledger (expense category) and
  bank account.
- Acceptance:
  - [ ] Expense report and account balance both reflect the posting
  - [ ] Editing an expense reverses + reposts without orphan entries
  - [ ] Posting is transactional

### S6-07 — Expense list + filters
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S6-03, S2-08
- Scope: List with filters by category, vendor, account, date range, amount.
- Acceptance:
  - [ ] Filters compose and persist
  - [ ] Bulk actions respect permissions
  - [ ] Export hook available

**Sprint 6 exit criteria:** expenses are captured, categorized, attached, recurring,
and correctly reflected in accounts and reports.

### Sprint 6 — Delivery notes

- **Shipped:** `expense_categories` (defaults seeded on company creation + custom),
  `vendors`, `expenses` tables/models/factories; policies; `ExpenseService` (record,
  update with ledger reversal-and-repost, void, recurring generation);
  `expenses:generate-recurring` command scheduled daily; expense CRUD, list with
  category/vendor/account/date filters and a filtered total, detail page with
  attachment download and void; safe attachment storage (private `local` disk,
  MIME + size validation, authorized download); vendor CRUD; category management page;
  nav for Expenses and Vendors.
- **Test coverage:** 18 new tests (default category seeding, category add/duplicate/
  in-use guard, vendor CRUD + delete guard, expense post/update/void/validation/
  attachment accept-reject/filters, recurring generation/skip/voided, cross-tenant
  denial). Full suite: 246 passing; the 32 failures remain the pre-existing broken
  scaffold admin/SEO tests.
- **Decisions:** expense `amount` is the total paid (gross) and `tax_amount` is the
  tax included within it (for tax reporting); paying from an account posts an
  `expense`/out ledger entry, while editing/voiding leaves the original entry intact
  and adds a counter-entry (auditable). Recurring templates generate child expenses
  and advance `next_recurrence_on` (idempotent per due date). Attachments live on the
  private disk and are only served through an authorized route.
- **Bug caught:** recurring children created from the console had no active tenant
  context, so `company_id` was not stamped; the service now sets it explicitly.

---

---

## Sprint 7 — Transactions & Chart of Accounts

**Goal:** Establish the ledger backbone: chart of accounts, the transaction/posting
service, transaction history, transfers, adjustments, and direct income.

---

### S7-01 — Chart of accounts schema + default seed
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-02
- Scope: Ledger accounts grouped by Assets/Liabilities/Equity/Revenue/Expenses with a
  sensible default set per company.
- Acceptance:
  - [ ] Default chart seeded on company creation
  - [ ] Accounts typed and tenant-scoped
  - [ ] Reports resolve against these accounts

### S7-02 — Ledger account CRUD + hierarchy
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S7-01
- Scope: Manage ledger accounts and parent/child hierarchy.
- Acceptance:
  - [ ] Hierarchy renders and aggregates correctly
  - [ ] Accounts with postings cannot be deleted
  - [ ] Type is immutable once posted

### S7-03 — Transaction ledger schema + posting service
- Type: Feature · Priority: P0 · Est: L · Status: DONE (delivered early in Sprint 5)
- Depends on: S7-01, S0-11
- Scope: Central `transactions` table + a single posting service that all modules
  (invoices, payments, expenses, credits, adjustments) call; links to source docs and
  accounts.
- Acceptance:
  - [ ] All financial effects flow through this service
  - [ ] No module mutates balances directly
  - [ ] Posting is transactional, idempotent, and audited

### S7-04 — Transaction list + filters
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S7-03, S2-08
- Scope: Unified transaction list (date, description, type, account, amount) with
  filters.
- Acceptance:
  - [ ] Types (Income/Expense/Invoice/Payment/Transfer/Refund/Adjustment) shown clearly
  - [ ] Filters by date/type/account/category/amount work
  - [ ] Drill-through to source document

### S7-05 — Transfers between accounts
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S7-03, S5-01
- Scope: Move funds between bank/cash accounts with paired postings.
- Acceptance:
  - [ ] Both accounts reflect the transfer
  - [ ] Total across accounts is conserved
  - [ ] Transfer is auditable

### S7-06 — Adjustments & transaction void/reversal
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S7-03, S0-10
- Scope: Manual adjustments and reversal of erroneous transactions with reason.
- Acceptance:
  - [ ] Reversal preserves the original entry and posts a counter-entry
  - [ ] Balances return to the correct state
  - [ ] Fully audited

### S7-07 — Income management (direct income)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S7-03, S5-01
- Scope: Record direct/other income not tied to an invoice; income views and filters.
- Acceptance:
  - [ ] Direct income posts to revenue and an account
  - [ ] Income report includes it correctly
  - [ ] Tenant-scoped and audited

**Sprint 7 exit criteria:** a single, auditable ledger is the source of truth for all
balances, with transfers, adjustments, and direct income supported.

### Sprint 7 — Delivery notes

- **Shipped:** `ledger_accounts` chart of accounts (default seed with asset/liability/
  equity/revenue/expense hierarchy + custom/child accounts) and `LedgerAccountType`;
  transactions now optionally link to a ledger account; chart of accounts page with
  rolled-up movements and account CRUD; unified transaction list with type/direction/
  account/ledger-account/date filters, search, income/expense/net summary, and source
  drill-through; a "new entry" page for **transfers**, **adjustments** and **direct
  income**; reversal of manual entries; nav for Transactions and Chart of Accounts.
- **Test coverage:** 12 new tests (chart seeding/hierarchy, render, staff denial,
  child create, delete guards, transfer balances, income posting to a revenue account,
  adjustment + reversal, system-transaction reversal blocked, transaction filters,
  cross-tenant ledger denial). Full suite: 258 passing; the 32 failures remain the
  pre-existing broken scaffold admin/SEO tests.
- **Note:** `S7-03` (ledger + posting service) shipped early in Sprint 5; this sprint
  builds the chart-of-accounts, transaction UI and manual-entry workflows on top.
  Balances remain derived from transactions; reversals add counter-entries rather than
  mutating history.
- **Bug caught:** adjustment entries with optional account fields raised an undefined
  key; now handled with null-coalescing.

---

---

## Sprint 8 — Reports

**Goal:** Deliver the MVP's financial reporting: P&L, income, expenses, receivables,
tax summary, and customer statements — all exportable and tenant-scoped.

---

### S8-01 — Profit & Loss report
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S7-03
- Scope: Revenue vs expenses → net profit for a date range, grouped by account.
- Acceptance:
  - [ ] Figures reconcile with transactions
  - [ ] Opening balances excluded from P&L
  - [ ] Date-range boundaries respect financial year

### S8-02 — Income report
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S7-03
- Scope: Total income, income by customer, income by month.
- Acceptance:
  - [ ] Totals match payments + direct income
  - [ ] Grouping/filters correct
  - [ ] Exportable

### S8-03 — Expense report
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S6-06
- Scope: Expenses by category, date, and vendor.
- Acceptance:
  - [ ] Totals reconcile with postings
  - [ ] Grouping/filters correct
  - [ ] Exportable

### S8-04 — Accounts Receivable (aging)
- Type: Feature · Priority: P0 · Est: L · Status: DONE
- Depends on: S3-06, S5-03
- Scope: Outstanding + overdue invoices, aging buckets, customer balances.
- Acceptance:
  - [ ] AR = sum of unpaid balances (net of credits)
  - [ ] Aging buckets are correct
  - [ ] Early-payment/credit edge cases handled

### S8-05 — Tax Summary
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S3-05, S6-06
- Scope: Tax collected, tax paid, taxable sales for a period.
- Acceptance:
  - [ ] Collected = invoice tax on recognized sales
  - [ ] Paid = input tax on expenses
  - [ ] No jurisdiction-specific rules hard-coded

### S8-06 — Customer Statement (+ PDF/email)
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S2-03, S8-04, S4-03
- Scope: Opening balance, invoices, payments, closing balance; view, PDF, email.
- Acceptance:
  - [ ] Statement reconciles to customer balance
  - [ ] Opening balance is period-aware
  - [ ] PDF/email reuse delivery infrastructure

### S8-07 — Date-range presets & period comparison
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S8-01
- Scope: Shared report date controls (this month, quarter, FY, custom) and prior-period
  comparison.
- Acceptance:
  - [ ] Presets respect financial year
  - [ ] Custom ranges validate
  - [ ] Comparison renders consistently

### S8-08 — Report export (CSV / PDF)
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S8-01
- Scope: Export any report to CSV and PDF with applied filters.
- Acceptance:
  - [ ] Exported data matches on-screen results
  - [ ] Exports are tenant-scoped
  - [ ] Large exports are queued when needed

### S8-09 — Report role-based access & tenant scoping
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S1-04, S1-05
- Scope: Enforce report permissions per role and prove tenant isolation for every
  report endpoint.
- Acceptance:
  - [ ] Staff/Accountant/Owner report access matches policy
  - [ ] Cross-tenant report access is impossible
  - [ ] Covered by feature + isolation tests

**Sprint 8 exit criteria:** all listed reports are accurate, filterable, exportable,
permission-aware, and tenant-safe.

### Sprint 8 — Delivery notes

- **Shipped:** `ReportService` (profit & loss, income, expenses, receivables with
  aging, tax summary, customer statement) returning a normalised report structure;
  `ReportController` with a reports index and one route per report; shared date-range
  controls (defaults to the company financial year); CSV + PDF export for every
  report; a customer statement page and email (PDF attached via `StatementMail`);
  customer opening balances posted as ledger adjustment entries (source = customer,
  Accounts Receivable) and reflected in the profile, statement and receivables;
  nav for the Reports group; statement link + opening-balance form on the customer page.
- **Test coverage:** 10 new tests (P&L, tax, receivables aging, statement closing
  balance, report access + staff denial, CSV/PDF export, opening-balance ledger entry,
  statement inclusion, staff denial). Full suite: 268 passing; the 32 failures remain
  the pre-existing broken scaffold admin/SEO tests.
- **Decisions:** reports are read-only and derive from documents + ledger. Revenue is
  accrual-based (issued invoices − credit notes + direct income); receivables are
  invoice balances plus customer opening adjustments. Report access requires the
  `view-reports` ability (owner/accountant). Opening balances are ledger entries, not
  stored fields, satisfying the S2-07 invariant.
- **Carry-over completed:** `S2-07` opening balances is done, which clears the last
  Sprint 2 blocker.

---

---

## Sprint 9 — Dashboard & Notifications

**Goal:** Surface the financial overview on the dashboard and wire transactional email
notifications.

---

### S9-01 — KPI cards
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S7-03
- Scope: Total Revenue, Total Expenses, Outstanding Invoices, Overdue Invoices, Net
  Income.
- Acceptance:
  - [ ] Each KPI reconciles with its report
  - [ ] Period selector applies consistently
  - [ ] Empty/zero states render cleanly

### S9-02 — Revenue vs Expenses chart
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S8-01
- Scope: Monthly revenue-vs-expense comparison chart.
- Acceptance:
  - [ ] Values match P&L monthly grouping
  - [ ] Accessible/legible (labels, tooltips)
  - [ ] Responsive

### S9-03 — Revenue trend
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S8-02
- Scope: Weekly/monthly revenue trend.
- Acceptance:
  - [ ] Granularity toggles correctly
  - [ ] Values reconcile with income report
  - [ ] Responsive

### S9-04 — Expense breakdown
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S8-03
- Scope: Expense breakdown by category.
- Acceptance:
  - [ ] Percentages sum to 100%
  - [ ] Matches expense report
  - [ ] Responsive

### S9-05 — Recent transactions & outstanding invoices widgets
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S7-04, S8-04
- Scope: Recent transactions (date/description/type/amount/account) and outstanding
  invoices (customer/invoice/amount/due/status).
- Acceptance:
  - [ ] Lists link through to full modules
  - [ ] Data matches source lists
  - [ ] Tenant-scoped

### S9-06 — Email notification templates + toggles
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S4-03, S4-06
- Scope: Invoice sent, payment received, invoice due, invoice overdue; per-company
  enable/disable and template content.
- Acceptance:
  - [ ] Each notification sends on its trigger
  - [ ] Toggles suppress correctly
  - [ ] Templates support documented variables

**Sprint 9 exit criteria:** the dashboard gives an accurate, live financial overview and
notifications fire reliably.

### Sprint 9 — Delivery notes

- **Shipped:** `DashboardService` (period KPIs, 6-month revenue-vs-expenses series,
  monthly/weekly revenue trend, expense breakdown, recent transactions, outstanding
  invoices) built on `ReportService`; a rebuilt responsive `Dashboard` with period
  presets (month/quarter/year/financial year) and a monthly/weekly trend toggle,
  CSS bar charts (no new dependency), and widgets linking into the modules; per-company
  **notification toggles** (`notify_invoice_sent`, `notify_payment_received`,
  `notify_invoice_due`, `notify_invoice_overdue`) surfaced in Email settings and enforced
  across the invoice-email, payment-receipt and reminder flows.
- **Test coverage:** 7 new tests (dashboard KPIs/charts/widgets, staff access, period +
  trend switch, toggle persistence, invoice-email block, payment-receipt block + auto-send
  skip, due-reminder suppression). Full suite: 275 passing; the 32 failures remain the
  pre-existing broken scaffold admin/SEO tests.
- **Decisions:** dashboard KPIs reuse the same report definitions as Sprint 8 (accrual
  revenue, expenses, receivables) so figures reconcile across screens. Charts are simple
  CSS/SVG bars to avoid adding a charting dependency for the MVP. Notification toggles
  gate both automated and entry-point sends; disabling shows a clear message.

---

---

## Sprint 10 — Settings, Search, Polish & Launch

**Goal:** Complete configuration, global search, accessibility/UX polish, legal
requirements, and prove the launch journey end-to-end.

---

### S10-01 — Business settings
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S0-02
- Scope: Company information, logo, address, currency, tax, financial year.
- Acceptance:
  - [ ] Changes propagate to invoices/PDFs/reports
  - [ ] Logo upload validated/stored safely
  - [ ] Only owners can change company-critical settings

### S10-02 — Invoice settings
- Type: Feature · Priority: P0 · Est: M · Status: DONE
- Depends on: S0-11, S4-01
- Scope: Prefix, numbering, default terms, footer, logo.
- Acceptance:
  - [ ] New numbering applies to new invoices only
  - [ ] Footer/terms appear on PDF
  - [ ] Invalid prefixes rejected

### S10-03 — Tax settings
- Type: Feature · Priority: P1 · Est: M · Status: DONE
- Depends on: S3-05
- Scope: Configurable tax rates and defaults; explicitly no hard-coded country rules.
- Acceptance:
  - [ ] Rates manageable per company
  - [ ] Defaults apply to new invoices/products
  - [ ] Changes don't retroactively alter issued invoices

### S10-04 — Global/module search
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S2-02, S3-07, S5-09, S6-07, S7-04
- Scope: Search across customers, invoices, payments, expenses, transactions with
  filters.
- Acceptance:
  - [ ] Results are grouped by module and tenant-scoped
  - [ ] Query is fast enough on seeded volume
  - [ ] Keyboard-accessible

### S10-05 — Accessibility & responsive pass
- Type: Chore · Priority: P0 · Est: L · Status: DONE
- Depends on: S0-07
- Scope: Audit and fix portal accessibility (semantics, labels, focus, contrast,
  keyboard) and responsive behavior.
- Acceptance:
  - [ ] Key flows are keyboard-operable
  - [ ] Forms have proper labels/errors/aria
  - [ ] Usable on tablet and mobile widths

### S10-06 — Empty/loading/error states + bulk action consistency
- Type: Chore · Priority: P1 · Est: M · Status: DONE
- Depends on: S2-08
- Scope: Consistent states and bulk actions across all lists.
- Acceptance:
  - [ ] Every list has empty/loading/error states
  - [ ] Bulk actions confirm and report results
  - [ ] Failures never leave partial state silently

### S10-07 — Legal pages, account deletion & tenant data export
- Type: Feature · Priority: P1 · Est: L · Status: DONE
- Depends on: S0-14
- Scope: ToS, privacy policy, cookie/consent; self-service account deletion; per-tenant
  data export.
- Acceptance:
  - [ ] Legal pages linked from auth/portal footer
  - [ ] Deletion is confirmed, scoped, and audited
  - [ ] Export produces a complete, portable archive

### S10-08 — Demo seeder, Section 31 script & launch QA
- Type: Chore · Priority: P0 · Est: L · Status: DONE
- Depends on: all P0 tickets
- Scope: A seeded demo company matching Section 31 plus a runbook; full QA of the demo
  flow, tenant isolation, and backup restore.
- Acceptance:
  - [ ] Demo flow completes exactly as specified end-to-end
  - [ ] Tenant-isolation suite passes
  - [ ] Backup restored successfully in a staging check

**Sprint 10 exit criteria:** the product is configurable, accessible, legally presentable,
and the Section 31 journey runs flawlessly on demand.

### Sprint 10 — Delivery notes

- **Shipped:** `SettingsController` + pages for **Business** (profile, logo upload,
  currency with a lock once financial documents exist, timezone, financial year),
  **Invoice settings** (prefix, padding, default terms/footer, live next-number preview)
  and **Tax settings** (registration number, default rate, inclusive/exclusive); global
  **search** across customers/invoices/payments/expenses/transactions with role-scoped
  groups and a header search box; **account deletion** (self-service, blocks the last
  owner, deactivates and anonymises the account and removes memberships);
  **tenant data export** (`TenantDataExporter`) producing a ZIP of CSVs;
  public **Terms** and **Privacy** pages wired into the auth footer; accessibility
  improvements (skip link, `main` landmark, labelled search, `sr-only` utility);
  `DemoDataSeeder` reproducing the Section 31 journey.
- **Test coverage:** 17 new tests (business/invoice/tax settings, currency lock, logo
  upload, staff denial, tenant export download, search across modules + role scoping +
  short queries, legal pages, account deletion rules, demo seeder/report reconciliation).
  Full suite: 288 passing; the 32 failures remain the pre-existing broken scaffold
  admin/SEO tests.
- **Decisions:** currency is locked once any financial document exists; account deletion
  anonymises rather than hard-deletes so historical records stay referentially intact;
  exports are generated on demand from the tenant's own data.
- **Bulk actions (S10-06, completed):** `DataTable` gained multi-select with an
  action bar (confirm + apply); customers and products support bulk archive/activate
  via `POST /portal/customers/bulk` and `/portal/products/bulk`, with tests.
- **Inherited scaffold cleanup (post-sprint):** the previously-failing scaffold tests
  now pass, bringing the suite fully green. Fixes: bound the Laravel app to Pest `Unit`
  tests; added the missing `admin.pages.show` view; fixed `x-admin.card` to render the
  `header` slot (headers were silently dropped across several admin views); fixed the
  `SiteSetting` JSON mutator (array-to-string error when `type` was set after `value`);
  reconciled `SEOAnalyzerService` scoring with its tests; and updated stale admin/SEO
  tests that asserted a JSON API the Blade admin never exposed.

---

---

## Section 31 Demo Path — Ticket Mapping

The P0 chain that must work for the Navneet demonstration.

| Demo step | Tickets |
|---|---|
| 1. Dashboard shows revenue, expenses, profit, outstanding, overdue | S9-01, S9-02, S9-05 |
| 2. Create customer "ABC Consulting" | S2-01, S2-02 |
| 3. Create service "Business Consulting — $2,500" | S2-04 |
| 4. Create invoice `INV-0001` for $2,500 | S3-01, S3-02, S3-03, S3-06 |
| 5. Send invoice (professional PDF generated) | S4-01, S4-02, S4-03 |
| 6. Record payment of $1,500 | S5-03, S5-04, S7-03 |
| 7. Dashboard updates: Revenue $2,500 / Paid $1,500 / Outstanding $1,000 | S9-01, S3-06 |
| 8. Add expense "Software Subscription — $300" | S6-03, S6-06 |
| 9. Profit & Loss: Revenue $2,500 / Expenses $300 / Net $2,200 | S8-01 |

---

## Deferred Backlog (Phase 2 / Phase 3)

Explicitly out of MVP scope; recorded so scope stays controlled.

**Phase 2 — delivered so far**
- Recurring invoices (`invoices:generate-recurring` generates draft occurrences from a
  recurring invoice template).
- SMS notifications (driver-based: log/twilio/vonage/generic HTTP; per-company toggle,
  invoice + reminder SMS, test send).
- Online payments with Stripe (per-company settings, public invoice "Pay now" checkout,
  signed webhook that records the payment idempotently).
- Quotes (create/list/show, status lifecycle, and one-click conversion to a draft
  invoice).
- Bank statement import & reconciliation (CSV import into an account, auto-match to
  ledger transactions, manual match/unmatch, create a transaction from a line, and
  reconcile toggling).

**Banking & reconciliation**
- Live bank connections (open banking APIs).

**Advanced accounting**
- Double-entry journal UI, journal entries, general ledger, trial balance, balance
  sheet, accounts payable, budgets, fixed assets, purchase orders.

**Sales & expenses extensions**
- Recurring payments, expense approvals, receipt OCR.

**Automation**
- Auto reminders beyond MVP, recurring transactions, automatic categorization.

**Integrations & platforms**
- Additional payment gateways (PayPal, Razorpay, Mollie, Xendit), payroll, inventory,
  mobile apps.

**AI**
- Natural-language accounting assistant querying structured data (Phase 3
  differentiator).

---

## Definition of Done & Quality Gates

A ticket is `DONE` only when all of the following hold:

- [ ] Acceptance criteria all checked.
- [ ] Tenant isolation preserved — company A cannot read or write company B data.
- [ ] Authorization enforced server-side (UI hiding is not sufficient).
- [ ] Financial effects flow through the ledger posting service; no direct balance
      mutation.
- [ ] Financial records are voided with reason and audit-logged, never hard-deleted.
- [ ] Money uses integer minor units and documented rounding.
- [ ] Errors are handled and surfaced; no silent failures on money/email/queue paths.
- [ ] Relevant Pest feature test(s) added; existing suite stays green.
- [ ] `./vendor/bin/pest` passes.
- [ ] `./vendor/bin/pint` passes (PHP).
- [ ] `npm run lint` passes (TypeScript) for touched portal code.
- [ ] Accessibility considered for any new UI.

### Per-sprint gate
At the end of each sprint, run the full test suite, the tenant-isolation suite, and a
manual smoke of that sprint's user flow before marking the sprint complete.

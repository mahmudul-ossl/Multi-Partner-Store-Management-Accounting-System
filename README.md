# Multi-Partner Store Management & Accounting System

A production-ready **multi-partner store management, inventory, sales, accounting, investment, withdrawal, promotion, approval, and reporting system** built with Laravel.

The system is designed for businesses where multiple partners jointly operate a store, contribute investments, withdraw funds, spend money on promotions, manage inventory, process sales, and share business profits.

---

## Table of Contents

* [Overview](#overview)
* [Key Features](#key-features)
* [Core Business Workflow](#core-business-workflow)
* [Partner Management](#partner-management)
* [Approval System](#approval-system)
* [Investment & Withdrawal](#investment--withdrawal)
* [Inventory Management](#inventory-management)
* [Sales Management](#sales-management)
* [Promotion Management](#promotion-management)
* [Expense Management](#expense-management)
* [Accounting](#accounting)
* [Reports](#reports)
* [User Roles](#user-roles)
* [Technology Stack](#technology-stack)
* [System Architecture](#system-architecture)
* [Database Modules](#database-modules)
* [Installation](#installation)
* [Docker Setup](#docker-setup)
* [Environment Configuration](#environment-configuration)
* [Database Setup](#database-setup)
* [Development Workflow](#development-workflow)
* [Testing](#testing)
* [API](#api)
* [Security](#security)
* [Accounting Principles](#accounting-principles)
* [Inventory Principles](#inventory-principles)
* [Future Improvements](#future-improvements)
* [License](#license)

---

# Overview

This application manages the complete financial and operational activities of a multi-partner store.

The system supports:

* Multiple business partners
* Individual partner investments
* Partner withdrawals
* Partner expenses
* Partner promotion contributions
* Partner profit allocation
* Partner-to-partner transfers
* Multi-level transaction approval
* Product management
* Supplier management
* Purchase management
* Inventory management
* Sales management
* Customer management
* Promotion management
* Business expenses
* Cash and bank accounts
* Double-entry accounting
* General ledger
* Trial balance
* Profit & Loss
* Balance Sheet
* Monthly business reports
* Partner statements
* Audit logs
* Notifications
* Role-based permissions

The primary goal is to maintain a **single source of truth for financial and inventory data**.

---

# Key Features

## Partner Management

Manage multiple business partners.

Each partner can have:

* Name
* Partner code
* Phone
* Email
* Address
* Joining date
* Ownership percentage
* Investment percentage
* User account
* Status

Partner statuses:

* Active
* Inactive
* Suspended

---

## Partner Financial Management

Track each partner independently.

The system records:

* Investments
* Withdrawals
* Promotion contributions
* Business expenses
* Profit shares
* Capital transfers
* Adjustments

Each partner has a complete statement.

Example:

| Date   | Description  |   Debit |   Credit |  Balance |
| ------ | ------------ | ------: | -------: | -------: |
| 01-Jan | Investment   |         | ৳500,000 | ৳500,000 |
| 10-Feb | Promotion    | ৳20,000 |          | ৳480,000 |
| 15-Mar | Withdrawal   | ৳50,000 |          | ৳430,000 |
| 31-Mar | Profit Share |         |  ৳25,000 | ৳455,000 |

---

# Approval System

The system uses a **Partner Approval / Dual-Control workflow** for important transactions.

A user who creates a transaction cannot approve their own transaction.

### Example

```text
Partner A
   |
   | Creates withdrawal
   ↓
Pending Approval
   |
   ↓
Partner B
   |
   | Approves
   ↓
Approved
   |
   ↓
Accounting Entry
```

If rejected:

```text
Partner A
   |
   ↓
Withdrawal Request
   |
   ↓
Partner B
   |
   ↓
Rejected
```

The rejected transaction does not become a finalized accounting transaction.

---

# Approval Types

The approval system can be used for:

* Partner investments
* Partner withdrawals
* Partner expenses
* Promotion expenses
* Purchases
* Stock adjustments
* Refunds
* Partner transfers
* Large discounts
* Sales cancellations
* Other configurable transactions

---

# Multiple Approval Levels

The system supports configurable approval requirements.

Example:

```text
৳0 - ৳10,000
1 approval

৳10,001 - ৳100,000
2 approvals

৳100,000+
3 approvals
```

Approval thresholds should be configurable rather than hard-coded.

---

# Approval Rules

The following rules are mandatory:

1. A user cannot approve their own tr

---

# Phase 1 setup

The product specification above is preserved as provided. It ends mid-sentence in the source document. Everything from here is the Phase 1 setup and architecture guide for the implemented application.

Phase 1 delivers the Laravel application, Docker environment, authentication, users, roles and permissions, partner records (including an optional linked user), the audit log, and the admin UI. Investments, withdrawals, the approval workflow, inventory, sales, and the ledger are intentionally not built yet. The enums, permission names, money helper, self-approval guard, and balanced-entry guard are in place so those phases can plug in without renaming the access model.

Stack actually installed: **Laravel 13.10.1** (framework 13.33.0), PHP 8.3, Inertia.js 3, Vue 3, Tailwind CSS 4, Spatie Laravel Permission 8, MySQL 8, Redis, and the database queue/notification tables.

## Installation

Requirements on the host if you are not using Docker: PHP 8.3+ with `pdo_mysql`, `bcmath`, `intl`, `zip`, and `redis`; Composer 2; Node.js 22+; MySQL 8; Redis.

```bash
cp .env.example .env
composer install
php artisan key:generate
npm ci
npm run build
```

Set `SEED_SUPER_ADMIN_PASSWORD` and `SEED_DEMO_PASSWORD` in `.env` before seeding. The seeder refuses to run when those values are empty. The values in `.env.example` are for a local demo only. Change them before any shared environment.

Quote any value that contains `#` or a space. Dotenv treats an unquoted `#` as a comment, so `SEED_SUPER_ADMIN_PASSWORD=SuperAdmin#2026` is stored as `SuperAdmin` and the documented login fails. The example file keeps the full passwords:

```dotenv
SEED_SUPER_ADMIN_PASSWORD="SuperAdmin#2026"
SEED_DEMO_PASSWORD="Partner#2026"
```

## Docker Setup

`docker compose up -d` starts `app` (PHP-FPM), `nginx`, `mysql`, `redis`, a `queue` worker, and a `scheduler`. phpMyAdmin is optional and stays off unless you opt in. Production uses `docker-compose.prod.yml`: a multi-stage image, `www-data`, OPcache, and no published MySQL port. See [docs/deployment.md](docs/deployment.md).

```bash
cp .env.example .env
php artisan key:generate
npm ci && npm run build
docker compose up -d --build
docker compose exec app php artisan db:seed --force
```

Inside the containers, `DB_HOST` and `REDIS_HOST` are overridden to `mysql` and `redis`. The app container runs migrations on start. Seed is a separate command so production boots do not reload demo people.

Open the app at `http://localhost:8080` (or `APP_PORT`).

```bash
docker compose --profile tools up -d
```

That also starts phpMyAdmin on `PHPMYADMIN_PORT` (8081 by default).

Build the frontend before the first request. `public/build` is not committed, and nginx serves the mounted project directory.

## Environment Configuration

All credentials and connections come from the environment. Nothing in PHP hard-codes a database password, mail password, or Redis password.

| Variable | Purpose |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_KEY`, `APP_DEBUG`, `APP_URL`, `APP_TIMEZONE` | Application. Timezone defaults to `Asia/Dhaka`. |
| `APP_CURRENCY`, `APP_CURRENCY_SYMBOL`, `APP_DATE_FORMAT` | `BDT`, `৳`, `d-M-Y` (28-Sep-2026). |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_ROOT_PASSWORD` | MySQL. Root password is only for the Docker health check and server bootstrap. |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT` | Redis. |
| `CACHE_STORE`, `QUEUE_CONNECTION`, `SESSION_DRIVER` | `redis` in `.env.example`. |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Mail. New accounts send `AccountCreated` through the queue. |
| `SEED_SUPER_ADMIN_EMAIL`, `SEED_SUPER_ADMIN_PASSWORD`, `SEED_DEMO_PASSWORD` | Initial seed only. Quote passwords that contain `#`. |
| `TRUSTED_PROXIES` | Empty by default. Comma-separated proxy IPs/CIDRs, or `*` behind Cloudflare Tunnel so HTTPS forwarded headers are honored. |

To run on the host without Redis, set `CACHE_STORE=database`, `SESSION_DRIVER=database`, and `QUEUE_CONNECTION=database` after migrating.

## Database Setup

```bash
php artisan migrate --force
php artisan db:seed --force
```

Migrations:

| Migration | Tables |
| --- | --- |
| `0001_01_01_000000_create_users_table` | `users` (phone, `is_active`, soft deletes), `password_reset_tokens`, `sessions` |
| `0001_01_01_000001_create_cache_table` | `cache`, `cache_locks` |
| `0001_01_01_000002_create_jobs_table` | `jobs`, `job_batches`, `failed_jobs` |
| `2026_09_28_105336_create_permission_tables` | Spatie `permissions`, `roles`, and pivots |
| `2026_09_28_110000_create_notifications_table` | `notifications` |
| `2026_09_28_110100_create_partners_table` | `partners` |
| `2026_09_28_110200_create_audit_logs_table` | `audit_logs` (immutable, `created_at` only) |

`partners.ownership_percentage` and `partners.investment_percentage` are separate `decimal(8,4)` columns. Money in later phases must be `decimal(18,2)` via `App\Support\Money`, never float. Primary keys are bigint unsigned. Foreign keys and indexes are declared on the partner user link, status, and audit actor.

## Development Workflow

```bash
composer run dev
```

Or, separately:

```bash
php artisan serve
npm run dev
php artisan queue:work
```

Sign in at `/login`. The sidebar lists the screens your permissions allow, including Reports.

## Testing

Tests use PHPUnit (Pest 4’s current Laravel plugin requires PHP 8.4; this project targets PHP 8.3). The suite uses in-memory SQLite. `phpunit.xml` supplies the demo seed passwords so they are not hard-coded in PHP.

```bash
php artisan test
npm run build
```

## Security

- Session authentication on the `web` middleware group, which includes Laravel’s `PreventRequestForgery` CSRF middleware.
- Login failures are rate limited (5 attempts per email and IP, then a lockout), including `POST /api/v1/login`. The API allows 60 requests per minute. Approve and reject allow 30 per minute per user.
- Passwords use Laravel’s hashed cast and must be at least 10 characters with upper, lower, numbers, and symbols.
- Inactive users cannot sign in. A deactivated session is logged out.
- Policies authorize every user and partner action on the server. The Vue app only hides controls.
- A partner-role account can see only the partner row linked to that user.
- The last Super Admin cannot be demoted, deactivated, or deleted. Users cannot delete themselves.
- Audit rows redact passwords and cannot be updated or deleted.
- `App\Services\Approvals\SelfApprovalGuard` throws `You cannot approve your own transaction.` Approval, duplicate-approval, and permission checks run in `ApprovalService` with row locks. The UI cannot bypass them.
- Journals are posted only on final approval, must balance, and are reversed instead of deleted.
- `TRUSTED_PROXIES` controls `TrustProxies`. Leave it empty on a direct connection. Set `TRUSTED_PROXIES=*` only when the origin is reachable solely through a trusted proxy such as Cloudflare; otherwise list that proxy's addresses. Forwarded `https` is ignored until a proxy is trusted, which is what keeps asset URLs on HTTPS behind a tunnel.

## Default seeded logins

These match `.env.example`. Replace the passwords before sharing the environment.

| Role | Email | Password variable |
| --- | --- | --- |
| Super Admin | `superadmin@mpstore.test` | `SuperAdmin#2026` (`SEED_SUPER_ADMIN_PASSWORD="SuperAdmin#2026"`) |
| Admin | `admin@mpstore.test` | `Partner#2026` (`SEED_DEMO_PASSWORD="Partner#2026"`) |
| Accountant | `accountant@mpstore.test` | `Partner#2026` |
| Inventory Manager | `inventory@mpstore.test` | `Partner#2026` |
| Sales Manager | `sales@mpstore.test` | `Partner#2026` |
| Viewer | `viewer@mpstore.test` | `Partner#2026` |
| Partner (Rahim Uddin, Fatema Akter, Karim Hossain, Nusrat Jahan, Ayesha Siddiqua) | `rahim.uddin@mpstore.test` and the matching `first.last@mpstore.test` addresses | `Partner#2026` |

Eight partners are seeded. Ownership and investment percentages are different for several of them and both sum to 100. Shahidul Islam, Tanvir Ahmed, and Mahmuda Khatun have no login.

## What you should see

After migrate, seed, and `npm run build`:

1. Sign in as the super admin and land on a dashboard with partner and user counts, ledger cards (sales, investment, inventory value, expenses, profit, cash, bank, receivables, payables, pending approvals, low stock), and monthly charts. Inventory value is on-hand quantity times weighted average cost. The notification bell lists unread approval, low-stock, and payment-due alerts.
2. Partners can be searched, filtered, sorted, and paged. Creating one writes an audit row. Archiving soft-deletes it.
3. A partner can be linked to one unused user account, and that user cannot be linked twice.
4. Users can be created with a role. A weak password is rejected. The account-created notification is queued.
5. Roles shows the full permission matrix. Only Super Admin can open it.
6. Audit log shows login, logout, and user/partner changes. Amounts elsewhere format as `৳100,000.00`. Dates format as `28-Sep-2026`.

## Phase 2

Approvals, partner investments, withdrawals, transfers, partner dashboard, and partner statements are in place. Statements and capital totals are calculated from `journal_entry_lines` on accounts 3000 and 3200. Default thresholds are ৳0–10,000 = 1 approval, ৳10,001–100,000 = 2, and above ৳100,000 = 3, editable at Approval settings. The chart of accounts and the five financial accounts (Cash, DBBL Bank, BRAC Bank, bKash, Nagad) are seeded.

Seeded examples, posted through the real services: Rahim’s ৳8,000 investment (approved), Fatema’s ৳25,000 investment (approved), Karim’s ৳4,000 withdrawal (approved), Nusrat’s ৳20,000 withdrawal (pending), Shahidul’s ৳15,000 withdrawal (rejected, no journal), a ৳3,000 transfer from Rahim to Fatema (approved), and Karim’s ৳150,000 investment (partially approved, no journal).

Later phases should keep using `DocumentStatus`, `ApprovalRequestType`, and `JournalEntryService`. Promotion contributions, partner expenses, and profit share should use the source class names in `App\Support\LedgerSource` so the partner dashboard can split them. Inventory on-hand comes from `stock_movements` through `InventoryService` only.

## Phase 3

The ledger is the source of truth for cash, bank, and wallet balances.

- Chart of accounts is hierarchical and typed. Admins (`settings.manage`) add accounts. Seeded accounts are system accounts: code, type, normal balance, parent, and active flag stay fixed. Name and description can change. An unused non-system account can be removed; one with lines, children, or a financial account cannot.
- Financial accounts (cash, bank, mobile wallet) store an opening balance by posting it immediately: debit the asset account, credit 3300 Opening Balance Equity. The cached `current_balance` moves with each ledger line. Reconcile copies the ledger total back onto the cache.
- Manual journals and transfers between financial accounts (for example DBBL Bank to bKash) wait for approval. `accounting.manage` is the approve permission. A requester cannot approve their own document. The journal posts only on the final approval.
- Journal list and detail can reverse a manual journal, an account transfer, or an opening-balance entry. Partner investments, withdrawals, and transfers are reversed from those documents. A reversal swaps the sides, so the net on the account is zero.
- General ledger shows opening balance (movement before the from-date, signed by normal balance), each line, and a running balance. Cash report lists cash accounts. Bank report lists banks, then mobile wallets.
- `accounting.view` (Accountant, Admin, Viewer) opens the screens. `accounting.manage` (Accountant and Admin) creates journals, financial accounts, transfers, reconciliations, and reversals. Partners do not have either permission.

Seeded examples, posted through the real services: Petty Cash (chart 1001, under Cash) opened at ৳5,000; April bank charges ৳1,500 approved and posted against Cash; a ৳2,000 rent accrual left pending with no journal; and a ৳2,000 transfer from DBBL Bank to bKash approved and posted.

## Phase 4

Costing is weighted average. `InventoryService` is the only writer of `stock_movements`. On-hand quantity is the sum of those signed quantities. `products.average_cost` is updated in the same transaction. Phase 5 COGS must call `InventoryService` when stock leaves and use the `unit_cost` stored on that movement (the average at issue time).

- Categories, brands, units, warehouses, suppliers (contacts and a due list), and products (unique SKU and barcode, prices, minimum stock, reorder level, status, description, image).
- Opening stock, adjustments, and damage are stock documents. They change quantity and post a journal only after the final `stock.adjust.approve` approval. The requester cannot approve their own document.
- Purchases, purchase returns, and supplier payments use `purchase.approve`. Stock and the journal post only when that approval is final. A purchase debits 1100 Inventory and credits the cash, bank, or wallet account for the amount paid and/or 2000 Accounts Payable for the amount due. A return debits payable and credits inventory at the original unit cost, and reduces the purchase due. A supplier payment debits payable and credits the financial account.
- Low stock is on-hand at or below `reorder_level`. The stock report shows inventory value. The movement report lists the ledger.

Seeded through the real services: 5 suppliers, 5 categories, 3 brands, 20 leather goods with opening stock, one approved partial purchase of bifold wallets (then a return and the remaining payment), one pending tote purchase, and the Leather Key Fob left at or below its reorder level. Cash stays non-negative after those payments.

## Phase 5

A sale total is subtotal − discount + delivery. Completing it is the only time stock leaves and the journal posts: debit cash and/or 1200 Accounts Receivable, credit 4000 Product Sales, and debit 5000 COGS, credit 1100 Inventory. COGS uses the `unit_cost` on the sale movement, which is the weighted average at that moment. A later sale of the same product uses the average after this one.

- Discounts above the large-discount threshold (default ৳1,000, editable on Approval settings) stay pending. Stock and the journal wait for `sale.cancel` approval. The requester cannot approve it.
- A sales return posts immediately. Stock comes back at the original sale cost, revenue and COGS reverse in proportion to the line (including its share of discount and delivery), and the sale due falls. A negative due is customer credit.
- A refund pays that credit only after approval. It debits receivable and credits cash. Cancellation of a completed sale with no returns, payments, or refunds also needs approval; on approval the original journal is reversed and the quantity is put back.
- Customer dues are the sum of `due_amount` on completed sales. The dashboard sales card is product-sales revenue from the ledger.

Seeded through the real services: four customers, a completed cash sale, a completed sale with discount, delivery, a partial payment and a later receipt, and one messenger-bag order held for a ৳1,500 discount.

## Phase 6

Promotions record a platform, dates, budget, and status. `actual_amount` is the sum of approved contributions, not a number typed in by hand. A contribution (`App\Models\PromotionPartnerExpense`) waits for `promotion.approve`. On approval the journal debits 5100 Promotion Expense. Partner-paid contributions credit that partner’s 3000 capital, so the partner statement and dashboard promotion figure come from those capital lines. Business-paid contributions credit the cash, bank, or wallet account and do not change partner capital.

Expenses use the categories Rent, Salary, Electricity, Internet, Packaging, Delivery, Marketing, Facebook Ads, Software, Transport, Bank Charges, Office Expense, and Misc. Each category posts to its own chart account (Misc stays on 5600). They wait for `expense.approve` under the same amount bands as other approvals. A partner-paid expense credits that partner’s capital (`App\Models\Expense`). A business-paid expense credits cash or bank. The requester cannot approve their own contribution or expense. Customer delivery charged on a sale stays inside 4000. Account 5400 is the business’s own delivery expense.

Seeded through the real services: a Facebook campaign with Rahim’s ৳2,000 personal contribution approved and a ৳1,500 business boost left pending, an offline banner paid ৳500 from cash, May rent ৳800 from cash, Fatema’s personal electricity payment of ৳400, and a packaging expense left pending.

## Phase 7

Profit and loss, the balance sheet, and the trial balance read journal lines only. Revenue is the net credit on income accounts. Cost of goods sold is account 5000. Every other expense account is listed by category. Gross profit is revenue minus COGS. Net profit is gross profit minus those expenses. The profit-and-loss filters are today, this week (Monday start), this month, previous month, and a custom range.

The balance sheet is as of a date. Asset balances are debit minus credit. Liability and equity balances are credit minus debit, so partner withdrawals reduce equity. Current earnings are income minus expenses through that date. They are not closed into 3100 Retained Earnings. The page checks that assets equal liabilities plus equity.

The trial balance lists each account’s net on the debit or credit side, including income and expense, so the debit total equals the credit total.

A profit allocation (`App\Models\ProfitAllocation`) waits for `accounting.manage`. The requester cannot approve it. On approval the journal debits 3100 and credits 3000 for each partner. Ownership % and investment % are weights among active partners whose stored percentage is above zero; they do not have to add up to 100. Custom percentages must total 100.0000. Rounding half-up applies to every share except the last, which receives the remainder so the credits equal the debit. Those capital credits appear on the partner statement as profit share.

A period close stores the latest `closed_through` date. Every journal on or before that date is rejected, including a later approval of a document dated inside the closed period. The close date cannot move backward.

Seeded through the real services: ৳770 allocated by ownership on 30 Jun 2026 (Rahim ৳180, and the six active shares add up to ৳770), then the books closed through 31 May 2026. The allocation date stays open.

## Phase 8

Reports, the dashboard, operational notifications, the extended audit log, and the Sanctum API read the same services as the web screens.

- The report catalogue at `/reports` covers Sales, Purchases, Product sales, Stock, Stock movements, Investments, Withdrawals, Partner statement, Partner balances, Promotions, Expenses, Cash, Bank, Accounts receivable, Accounts payable, Profit and loss, Balance sheet, Trial balance, General ledger, and the Monthly business report. Each report keeps the permission that already guards that area. Date filter, search, sort, and pagination run before Excel and PDF export. Profit and loss, the balance sheet, and the trial balance still come from `FinancialStatementService`. Inventory value and stock reports come from `stock_movements` and the weighted-average cost.
- The monthly business report lists sales, purchases, COGS, gross profit, expenses by category, net profit, opening and closing cash, opening and closing stock, investment, withdrawal, promotion, receivables, and payables for the range.
- Dashboard cards and charts are omitted when the user lacks the permission. A partner sees their own investment, not the company-wide investment and withdrawal charts. Inventory value is filled from the stock ledger.
- Low stock, customer payment due, and supplier payment due write a database notification (one per user and subject). Approval notifications stay on mail and the database. The header bell lists the unread ones.
- Audit actions now include payment and stock adjustment, recorded when a supplier or customer payment posts and when a stock adjustment posts. Approve, reject, cancel, and reverse were already recorded.
- `/api/v1` uses Sanctum personal access tokens. Partners, investments, withdrawals, the partner statement, approvals (list, approve, reject), products, purchases, sales, and `reports/monthly` call the same form requests, services, and policies as the web. A requester still cannot approve their own transaction.

## Phase 9

Company-wide profit and loss, the balance sheet, and the trial balance are each gated by their own permission (`profit_loss.view`, `balance_sheet.view`, `trial_balance.view`). The partner role does not receive those permissions. A partner still opens their own dashboard, statement, and profit share. An administrator can grant any of the three permissions and that screen opens. The report catalogue uses the same permissions.

Login is limited to 5 attempts per email and IP, on the web form and on `POST /api/v1/login`. The API group is limited to 60 requests per minute. Approve and reject are limited to 30 requests per minute per user. Every application route other than login, the home redirect, and the health check requires `auth` or `auth:sanctum`, and the controller or its form request checks a policy or permission. Dashboard, logout, and marking your own notifications read are the authenticated self-service exceptions. Models keep `$fillable` and are not unguarded.

Reporting indexes cover sale, purchase, and expense status plus date, journal lines by account, stock movement dates, and approval request time. `Model::preventLazyLoading` is on outside production. Dashboard summaries are cached for 60 seconds and dropped when a journal posts, stock moves, or an approval is submitted, approved, rejected, or cancelled. Low-stock and payment-due notifications implement `ShouldQueue`, as approval and account-created notifications already did.

The production Compose file, OPcache, non-root PHP, health checks, queue worker, and scheduler are described in [docs/deployment.md](docs/deployment.md). Do not hard-delete financial history or stock movements.

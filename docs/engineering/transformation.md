# SalePro operational transformation

## Audit and implementation checklist

Audit baseline: 15 November 2026. Existing worktree contains extensive mobile, stock,
payment, translation and deployment customizations. Preserve these changes.
`view-inventory.json` inventories every backend and Manufacturing view, including
forms, modals, tables, charts and asynchronous operations. Static inspection is
not equivalent to browser acceptance.

- [x] Inventory dashboard, sales/POS, purchasing, quotations, products, categories,
  inventory/warehouses, suppliers, customers, expenses, accounting, reports,
  settings, users, Manufacturing, notifications and shared layouts.
- [x] Trace financial sources: `sales.paid_amount`, `payments`, `deposits`, customer
  `deposit/expense` caches, cash registers, accounts and cash-refunded `returns`.
- [x] Shared mobile table semantics, important fields, expandable details and
  DataTables responsive-column compatibility.
- [x] Compact working DataTables pagination, filtered counts and page sizes.
- [x] Shared viewport/component loaders, modal scrolling, keyboard/safe areas,
  touch targets, restrained motion, charts and readable chart summaries.
- [x] Header Sell/Buy and Finance/customer shortcuts with server permissions.
- [x] Purchase multi-selection using the existing productSearch row calculator;
  search remotely with limits rather than embedding the whole catalog.
- [x] Quick product modal reusing ProductController creation and custom fields.
- [x] Idempotent Stock supplier provisioning and explicit warehouse configuration.
- [x] Derived customer account, immutable deposit receipts/allocations, FIFO,
  excess credit, transactional reversals, cash accounting integration.
- [x] Account dashboard, filters, statement, printing/PDF and receipts.
- [x] Regression tests: calculations, permissions, retries, rollback, reversals,
  historical records, currency eligibility, responsive controls and workflows.
- [ ] Browser acceptance across 320/375/390/430/768/1024/1440px, all modules,
  French and RTL, slow/failing requests and keyboard interaction.

## Findings and architectural decisions

The existing shared shell already creates mobile record cards, hides advanced
order fields and reserves bottom-navigation space. Its DataTables controls still
wrap page numbers; Responsive can hide card fields; table decoration repeatedly
walks the complete DOM. Some chart loaders use page coordinates inside components.
The header lacks Buy and the bottom bar lacks Finance. Shared fixes must cover
all modules using the shell and preserve exports and desktop tables.

Purchasing embeds the complete catalog in JavaScript, adds one product at a time
and has no inline product creation. Reuse productSearch and its existing quantity,
batch, IMEI, unit, price and tax calculations. Serialize batch additions to avoid
row-index races; never replace edited existing lines.

Deposits currently update editable customer caches, have no payment method,
account, idempotency key or allocation history, and can be edited/deleted. New
receipts extend these existing deposits and create ordinary `Deposit` payments
for allocation. Allocation is a transfer from customer credit, not a second cash
receipt. Account cash reports must include receipt cash once and exclude those
managed allocation payments. Monetary calculations use integer minor units.

Account balance is available deposit credit minus unpaid eligible invoices.
Direct payments reduce invoices; allocating a deposit must not credit the ledger
twice. Existing cash returns represent a credit note plus an equal cash refund;
they do not automatically settle unpaid invoices. Historical discrepancies must
be surfaced, never silently rewritten. Managed receipts are append-only;
reversals undo their allocations in a transaction and retain the original audit.
Warehouse/staff restrictions also apply to eligible invoices. Foreign-currency
invoices require explicit currency handling and must not consume base-currency
deposits through an assumed conversion.

Default supplier is a purchasing setting, never product ownership. Warehouse
default must be configured explicitly and checked against permitted warehouses;
without a valid default, keep an empty required selection with guidance.

## Validation environment

The initial environment has Node 20, no PHP executable or local vendor directory,
and Docker socket access is denied. Disposable test tooling may be installed in
`/tmp`. Never run migrations on production or import records from `db.sql` for tests.
Deployment and production acceptance remain separate from repository changes.


## Implementation delivered in this checkout

The inventory now covers **205 views, 287 table declarations, 336 form
declarations and 11 chart canvases**, including invoice/mail/legacy copies and
Manufacturing. Counts describe source declarations, not distinct working screens.
Shared components cover operational list/create/edit pages, reports, settings,
users, suppliers, expenses, categories and warehouse/inventory screens that use
the shared layout. Invoice/print layouts retain their document formatting.

Mobile listings retain the actual table and original inputs/exports. They expose
customer/supplier, reference, date, quantities, amounts and status; other fields
can be expanded. Responsive-hidden DataTables columns are available in cards.
A compact previous/next/page-size controller delegates to the existing DataTables
API, preserving search and filtering. Desktop tables remain tabular. Charts have
readable summaries and accessible descriptions without changing their datasets.
Sticky actions exclude row-delete forms; loaders retain their animation while
being centered independently of the desktop sidebar.

Purchasing uses a capped, debounced remote catalog search, rejects stale results,
supports select-all/deselect, adds sequentially and preserves edited existing
lines. The inline modal creates standard products through ProductController,
including category/unit/tax and permitted required custom fields. Advanced
variant/batch/IMEI setup continues through the full product editor. Stock is a
supplier default; no product suppliers or past purchases are reassigned.
Quotation-to-purchase retains quoted selections and falls back to configured
defaults when the quote has none.

Customer account totals come from invoice/payment/deposit transactions. New
receipts and their payments are protected against edit/delete at both route and
model boundaries. FIFO allocations and reversals use customer row locks,
transactions and unique receipt tokens. Later POS spending of excess credit is
linked to the original receipts too. Allocations transfer credit; they are not
second cash receipts. Account statements, balance sheets, cash-register summaries
and cash-flow reports include the receipt once. Reversals affect the current
register rather than rewriting a previously closed register. Existing customer
cache totals must reconcile before new receipts are accepted. Historical
mismatches are displayed for accountant review; they are never auto-corrected.

**Accounting correction:** this installation records returns as full cash payouts
in its cash and account reports. Subtracting the same return from an unpaid
invoice also forgave debt after giving cash back. Customer due displays now use
invoice total minus actual paid amounts; the customer ledger shows the returned
product credit and its equal cash payout. No original transaction amounts or
records are rewritten. Supplier due and chart/revenue calculations are retained.
If the business requires returns that reduce debt without a cash payout, a
separate explicit credit-note settlement workflow is still needed.

## Database and operational instructions

Apply only the named migrations after backup and validation on a disposable copy:

1. Existing `2026_11_14_200000_create_operation_receipts_table.php` (if pending).
2. `2026_11_15_000100_create_purchase_defaults_table.php`.
3. `2026_11_15_000200_add_customer_deposit_receipts.php`.

The release script and isolated test runner include these migrations explicitly;
no production migration or deployment was run in this session. The Stock supplier
is preserved on rollback because purchases may reference it. Rolling back the
receipt migration after use would remove its audit metadata; retain a database
backup and prefer a forward repair. The existing `db.sql` is unchanged.

An administrator configures `/purchase-defaults`. Without a configured, active,
permitted warehouse, new purchases require a deliberate warehouse selection.
Customer names link to `/customer/{id}/account`; Finance also links to customer
accounts. Write access requires both customers-edit and sale-payment-add;
reversal additionally requires sale-payment-delete. Accounts spanning another
warehouse/staff scope require an authorized manager.

The old deposit form endpoint preserves entered amount/note and directs the user
to the complete account form. Callers already supplying the new receipt/date/
method/account fields can use that endpoint or the new account endpoint. Legacy
integrations omitting those financial details need to be updated; the endpoint
never silently invents a cash method, account or idempotency token.

Statement export uses the browser's **Print / Save as PDF** capability, with a
standalone A4 landscape layout. It does not add a server PDF renderer. Statements
show period opening/closing balances and activity; debt/available-credit cards
are explicitly labeled current values. Foreign-currency invoices are listed but
excluded from base-currency account totals and automatic allocations. Manual
allocation and arbitrary account adjustments were not added.

## Validation and remaining acceptance

- PHP 8.3.36 plus SQLite ran in WebAssembly, using dependencies copied to `/tmp`.
  Its unused network proxy was disabled; no application dependency was added.
- New account/default/view tests: **16 tests, 79 assertions passed**.
- Complete PHP suite: **60 tests, 201 assertions passed**, with one pre-existing
  empty-test-class warning. The stale dashboard scaffold test now verifies that
  guests are redirected to login instead of calling an undefined route name.
- JavaScript: shared navigation/forms/AJAX/dashboard/PWA/chart suites, purchase picker,
  real vendored DataTables pagination and all 21 sale-selection checks pass.
- Deployment/test-harness Python regressions: **30 passed**.
- Changed PHP source syntax parsed; Blade operational templates compiled; diff
  whitespace checks passed.
- Browser scenarios were extended for purchase defaults/inline product creation,
  customer deposits/reversals and the existing complete viewport matrix.
  **They could not run here:** Docker socket access is denied and Chromium exits
  with `setsockopt: Operation not permitted` in the restricted sandbox.

The checked implementation is **not production acceptance of the whole mission**.
Remaining work requires the isolated full browser run, actual phone/keyboard/
safe-area testing (including iOS and French/Arabic), screenshots, MySQL concurrency
and deadlock tests, production-sized performance checks and accountant review of
historical mismatches/return semantics. No passing browser report or production
release is claimed.

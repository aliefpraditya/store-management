# Changelog

All notable changes to Mitra Hub are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and versions follow [Semantic Versioning](https://semver.org/).

## [1.0.0] - 2026-11-16

First release: **R1 Core**, the day-to-day running of a store, online only (no offline/PWA). Scope and business rules are in [docs/analisis-sistem.md](docs/analisis-sistem.md) (in Indonesian).

### Added

#### Multi-store, access and audit
- One central database (stores, users, store access) and **one database per store**; a store is identified by the path `t/{store_code}/...`.
- Username login with three roles: **Super Admin** (central dashboard), **Store Admin** and **Cashier** (cashier screen only). Everyone except a Super Admin goes straight to their store.
- Store master with provisioning of the store database (migrations and seed data), including a log and a "Try again" button. Store databases are created by hand and never created or dropped automatically.
- User and store-access management; the minimum password length is a setting.
- Audit trail (laravel-auditing) in the central database and in every store database, including item price changes.

#### Items and prices
- Item list and form per store: SKU, name, category, brand, base unit and other units with **conversion**, cost per unit, selling price per unit, sellable units and the cashier's default unit, minimum stock. Prices are not rounded and carry no VAT.
- Two-way margin (price ↔ margin %, `price = cost / (1 − margin)`), a **conversion calculator**, and **bulk price change** from the item list with a preview; a change is refused when it no longer matches the preview.
- **Sealed packs** (e.g. a roll of wire): stock stays one number in the base unit, together with a count of the packs that are still whole. A pack can only be sold whole while it is whole, a smaller unit cuts loose pieces first and only then opens a pack, and void/return put whole packs back correctly.
- Per-store unit settings, **barcode label** printing from the SKU (Code 39), and sample data imports (items and opening stock).

#### Stock
- **Stock opname** is the only way to enter or correct stock outside transactions: opening stock, physical count, variance, reason, Admin review, then posting. It supports counting arrivals, counting whole packs, and a decision for damaged goods (dead stock, a DG item, or back to sellable stock).
- Three fixed warehouses: the sellable warehouse, **Returns Warehouse** and **Dead Stock**, and a stock history (ledger) for every movement.
- Returned goods wait in the Returns Warehouse until they are inspected through an opname; approving a return waits while the same goods are being inspected.

#### Sales and cashier
- **Cashier** screen with item search and barcode scan, best sellers in an empty cart, in-stock items listed before sold-out ones, and money fields with thousands separators.
- **Split payments** (cash, transfer, QRIS, EDC), change, printed receipts, and a receipt history. Every transaction carries a `client_uuid`, so a double submit does not make a second receipt.
- **Cancel** before payment is direct (and logged); **void** after payment needs an Admin.
- **COD** with a required delivery note, including voiding a COD receipt by a cashier or an Admin while the goods have not reached the customer.
- **Customer orders** (goods not in stock yet): deposit, goods marked as arrived and set aside from stock, then collected through a receipt. Cancelling gives the deposit back.
- **Delivery notes**: the driver is picked from the store's list when the note is shipped, several receipts can share one note, stock is not changed, and notes print in the store's own format.
- **Returns**: refund or exchange (exchange is the default), Admin approval with no day limit, refund accounts, and return printing.

#### Cash, expenses and closing
- **Open/close shift** with opening cash carried over from the previous shift's drawer, deposits, blind closing, and tiered differences: a difference that does not match is held for Admin/Super Admin review. The shift recap and the end-of-shift report print each deduction apart.
- **Expenses** from the cash drawer or another source, with categories.
- **Day closing** locks the transactions of that business date, with a daily report and a reopening by someone authorised.

#### Reports and store settings
- Daily and period sales reports (by payment method, by item, returns) and a simple **profit** report based on the last cost; the cost of units sent to dead stock is deducted from profit on the day the opname is posted.
- Store settings: automatic receipt printing, locking sales while an opname is counting, the variance limit that needs a note, and more.

### Known limitations
- Online only; offline, PWA and sync were dropped from the plan.
- Cost is the **last purchase price** (a receipt's cost of goods is an estimate); FIFO per layer comes in R2.
- Not included yet: purchases and container receiving, transfers between warehouses, price history, cross-store stock lookup and the audit trail screen (all R2). Credit, debt and customers are out of scope (old debts are information only).
- Store provisioning runs synchronously inside the request; a queued job waits for the Hostinger deployment decision.
- The still-open code review findings (all minor or accepted) are in [docs/hasil-review-kode.md](docs/hasil-review-kode.md).

### Upgrade notes
- Run `php artisan migrate` for the central database and `php artisan tenants:migrate` for every store database.
- Build the frontend assets with `npm run build` before deploying.

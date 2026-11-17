# Mitra Hub

A multi-store point-of-sale (POS) for retail stores in Indonesia. One central database holds the stores, users and store access; **each store has its own database**. The interface and the business documents are in Indonesian.

Release 1 (R1) covers the day-to-day running of a store: store master, items with per-unit prices and conversions, stock opname, and sales (cashier, shifts and cash, payments, receipts, returns, delivery notes, customer orders, expenses and day closing). It is online-only. See the [changelog](CHANGELOG.md) for what is in each release.

## Stack

- **Laravel 13** (PHP 8.3), server-side rendered through **Inertia.js 3**, session auth, no separate API
- **Vue 3** (JavaScript) with **Quasar 2**, bundled by Vite
- **MySQL** (tests also run on real MySQL, not SQLite)
- **stancl/tenancy 3.10**: database-per-store multi-tenancy, identified by path (`t/{store_code}/...`)
- **owen-it/laravel-auditing**: audit trail in the central and every store database

## Requirements

- PHP 8.3 and Composer
- Node.js and npm
- MySQL 8

## Getting started

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Set the MySQL connection in `.env` (the example file defaults to SQLite, which the app does not support), then:

```bash
php artisan migrate        # central database
npm install
composer run dev           # or: npm run dev
```

Store databases are **never created automatically**. Create an empty database for a store by hand (on Hostinger, in hPanel), register its credentials on the store's row in `stores`, then migrate it:

```bash
php artisan tenants:migrate
```

`php artisan db:seed` creates a demo Super Admin, a sample store (`mitra_utama`) with a Store Admin and a Cashier, for local development.

## Roles

| Role | Access |
|---|---|
| Super Admin | Central dashboard, store master, users and access, and every store |
| Store Admin | Everything inside their own store(s) |
| Cashier | The cashier screen only |

Everyone except a Super Admin goes straight to their store after login.

## Commands

```bash
npm run build                            # production frontend build
vendor/bin/pint --dirty --format agent   # code style, after touching PHP
php artisan test --compact               # full test suite (slow, real MySQL)
php artisan test --compact --filter=TestName
```

Tests run against the isolated `mitra_manager_test` database set in `phpunit.xml`. Several tests use `RefreshDatabase`, which drops every table, so never point them at the `.env` database or a store database.

## Architecture in short

```
HTTP request → Controller → Service → Model
```

- **Controllers** are thin: validate (Form Request), call a service, return an Inertia response.
- **Services** (`app/Services/`) hold the business logic.
- **Enums** (`app/Enums/`) hold status and type values.
- Tables and columns are English, snake_case and plural. Any method with two or more writes runs in `DB::transaction()`.
- Central routes live in `routes/web.php`; store routes in `routes/tenant.php` under `t/{tenant}/...` with the middleware `web` → `InitializeTenancyByPath` → `auth` → `store.access`.
- Sessions, cache and the queue are forced onto the central connection, because the default connection switches to the store database while tenancy is initialized. A model that lives in the central database must use the `CentralConnection` trait.
- Store database migrations are in `database/migrations/tenant`.

## Documentation

Read these before working on a feature (they are in Indonesian):

- [docs/analisis-sistem.md](docs/analisis-sistem.md): source of truth for scope and business rules
- [docs/wireframe.html](docs/wireframe.html) and [docs/diagram.html](docs/diagram.html): screens and flows (the `-r2` files are Release 2)
- [docs/estimasi-biaya.md](docs/estimasi-biaya.md): estimates
- [docs/hasil-review-kode.md](docs/hasil-review-kode.md): open code review findings

Release 2 and later scope (purchases, container receiving, transfers, FIFO costing, price history) is not part of R1 and should not be added unless asked.

## Deployment

Hosted on Hostinger: one hosting runs Laravel and serves the built Vue assets (`npm run build`). Databases are created by hand in hPanel (one user per database). There is no long-running worker on shared hosting, so use cron:

```
php artisan schedule:run
php artisan queue:work --stop-when-empty
```

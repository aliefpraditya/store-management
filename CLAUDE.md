# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this app does

Multi-store POS (app name: **Mitra Hub**; the repo, folder and central database keep the old name `mitra-manager`/`mitra_manager`) for Indonesian retail stores. One central database (stores, users, store access) plus **one database per store**. Phase 1 (R1) scope: store master, items (per-store, price per unit + conversion), stock opname, and sales (cashier, open/close cash, payments, receipts, returns, delivery notes, day close, expenses).

> **Before working on features, read the docs in [docs/](docs/)**: [analisis-sistem.md](docs/analisis-sistem.md) is the source of truth for scope and business rules (UI/docs are in Indonesian); [wireframe.html](docs/wireframe.html) and [diagram.html](docs/diagram.html) cover screens and flows (`-r2` variants are Release 2); [estimasi-biaya.md](docs/estimasi-biaya.md) holds estimates. Do not expand into Release 2+ scope unless asked.

## Stack

-   **Laravel 13** (PHP 8.3) — server-side rendering via Inertia.js, no separate API
-   **Vue 3** (JavaScript, no TypeScript) — frontend, bundled via Vite
-   **Quasar 2** — UI component library (via `@quasar/vite-plugin`, Quasar's own CSS — not Tailwind)
-   **Inertia.js 3** — SPA-style navigation; session auth (not Sanctum tokens)
-   **MySQL** — database (no SQLite in tests — uses real DB)
-   **stancl/tenancy 3.10** — database-per-store multi-tenancy, identified by path
-   **owen-it/laravel-auditing** — audit trail on models (central DB and every store DB)
-   **Laravel Boost** (dev) — MCP tools + guidelines (see the Boost block at the bottom)

Online-only: no PWA/offline/sync (R3 offline was dropped). One hosting, Laravel serves the Vue pages.

## Commands

```bash
# Local environment (Laragon) — MySQL does NOT start automatically
# start manually: D:\Laragon\bin\mysql\mysql-8.4.3-winx64\bin\mysqld.exe --defaults-file=my.ini  (root, no password)
# composer is not on the bash PATH: use PowerShell → & "D:\Laragon\bin\composer\composer.bat" <args>

# Frontend
npm run dev      # vite dev server
npm run build    # production build

# Laravel (run everything from the project root)
php artisan migrate
php artisan tenants:migrate    # migrations in database/migrations/tenant
composer run dev

# Code style (required after touching PHP)
vendor/bin/pint --dirty --format agent

# Tests (use real MySQL — no SQLite)
php artisan test --compact
php artisan test --compact --filter=TestName
php artisan test --compact tests/Feature/Tenancy/StoreAccessTest.php
```

## Architecture

### Backend layers

```
HTTP Request
  → Controller (app/Http/Controllers/)
  → Service    (app/Services/)          ← business logic lives here
  → Model      (app/Models/)
```

-   **Controllers** are thin: validate input (Form Request), call a Service, return an Inertia response.
-   **Services** hold all business logic. Each domain has one (e.g. a sales service, a stock-opname service). Create the `app/Services/` folder when the first service is needed.
-   **Enum** (`app/Enums/`) holds status/type enums used across models.
-   **Auditing**: models implement `OwenIt\Auditing\Contracts\Auditable` (central models and store models alike). Item price changes in R1 are only recorded here — they are the backfill source for R2's `price_histories`.
-   **Database naming**: tables and columns are **English, snake_case, plural** (pivot tables too, e.g. `store_users`). UI text and docs are Indonesian.
-   **DB transactions**: any method that performs 2+ Eloquent writes must wrap them in `DB::transaction()` — a single write needs no wrapper. **Never wrap an external HTTP call** inside a `DB::transaction()`.
-   **Idempotent submits**: transactional writes from the cashier carry a `client_uuid` to prevent double submit.

### Multi-store tenancy (stancl/tenancy, by path)

-   Central DB `mitra_manager`: `stores`, `users`, `store_users`, `roles`, `permissions`, `role_permissions`, `permission_user`, `store_provisioning_logs`, audits, sessions/cache/queue. Per-store DB (e.g. `store_mitra_utama`): all store data, migrations in `database/migrations/tenant`.
-   Store routes live in `routes/tenant.php` under `t/{tenant}/...` with middleware `web` → `InitializeTenancyByPath` → `auth` → `store.access`. Tenant key = `stores.code` (snake_case store name, e.g. `mitra_utama`).
-   `store.access` (`EnsureUserCanAccessStore`): user must be active, store `active`, and the user a super admin or linked in `store_users`.
-   **Store databases are never created/dropped automatically** (Hostinger hPanel: 1 user per DB). Connection details live in `stores.db_name/db_username/db_password/db_host` (password encrypted). Add store tables as migrations in the tenant folder.
-   **Pitfall — default connection switches to the store DB** while tenancy is initialized. Sessions/cache/queue are therefore forced onto the central connection in `config/session.php`, `config/cache.php`, `config/queue.php`. **Any new model that lives in the central DB must use the `CentralConnection` trait** (see `User`). In tests, DB column defaults (e.g. `is_active`) are absent on factory-built models unless set in the factory.
-   Roles & permissions: Super Admin (`users.is_super_admin`; central dashboard, cross-store stock lookup, roles page; holds every permission) · store roles are rows of the central `roles` table (system ones: Admin Toko, Kasir, Petugas Gudang; Super Admin adds more under Pusat → Peran & Izin). `store_users.role_id` gives a user their role, `role_permissions` its permissions, `permission_user` extra permissions for one user. Non-super-admins go straight to their store.
-   **Every store route must carry `permission:<name>[,<name>]`** (middleware `EnsureUserHasPermission`; any one listed permission passes). Permission names live in the `App\Enums\Permission` enum and must also exist as `permissions` rows (add them in a migration; a test fails while enum and table differ). Hide UI by the same permission (shared `auth.permissions`, or `can_*` flags on resources) but never rely on that alone.

### Routes

Routes are split by domain in `routes/`:

-   `web.php` — central pages (home, login, central dashboard)
-   `tenant.php` — per-store pages under `t/{tenant}/...`
-   `api.php` — JSON endpoints (none yet; the app uses session login, not tokens)
-   `console.php` — console commands

Prefer named routes and `route()` when linking.

### Frontend structure

```
resources/js/
  app.js            — Inertia bootstrap, registers Quasar (brand colors set here)
  Pages/            — Inertia page components (one per route)
```

Pages are resolved with `import.meta.glob('./Pages/**/*.vue')`; follow the pattern `Pages/[Domain]/Index.vue` and `Pages/[Domain]/Form.vue`. Add `Components/`, `Composables/`, `Layouts/` only when there is something to share — check for an existing component before writing a new one.

## Key domain concepts

-   **Store (toko)** — a tenant with its own DB; `receipt_prefix` (e.g. `MU`) is the receipt-number prefix, separate from the DB name `store_{snake_case_name}`. "Owner" = the store name; there is no owners table.
-   **Item prices** — per unit with unit conversion; **no rounding**, **no VAT**. Margin only: `price = cost / (1 − margin%)`, never markup. R1 uses the last purchase price (no FIFO yet; FIFO per layer arrives in R2).
-   **Stock opname** — the only stock input in R1 (no cost column). Purchases/container receiving are R2.
-   **Sales** — cashier flow: open/close cash, payment, receipt, return (exchange allowed; admin approval, no day limit), delivery note (does not change stock), day close (locks transactions), expenses (cash drawer or other source).
-   **Void** — before payment: direct, but logged; after payment: needs Admin approval.
-   **Credit/debt/customers/receivables** are not in Phase 1 (old debts are info only: `legacy_unpaid_amount`).

## Code Style & Engineering Principles

### The Holy Trinity

-   **Readability** — code is read far more than it's written; optimize for the next reader, not cleverness. Metric: a new developer should follow the flow without asking the author.
-   **Maintainability** — minimize the blast radius of a change. If a small feature risks regressions elsewhere, the design is too rigid.
-   **Testability** — if a class/function has too many dependencies or does too much to isolate in a unit test, it's already legacy the moment it's written.

### SOLID

-   **S — Single Responsibility**: one reason to change per class. Don't let a Model/Service mix persistence, business rules, and notification logic (see the layering in Architecture above — Controller/Service/Model split exists for this reason).
-   **O — Open/Closed**: prefer polymorphism (interfaces, Enum methods, strategy classes) over growing `if/elseif` chains keyed on a type string when adding a new variant.
-   **L — Liskov Substitution**: a subclass must not throw/narrow behavior the parent promises. Don't override a method to `throw` "not supported" — restructure the hierarchy instead.
-   **I — Interface Segregation**: prefer several small, specific interfaces/traits over one fat interface that forces unrelated implementations to stub out methods they don't need.
-   **D — Dependency Inversion**: depend on abstractions (interfaces, injected services) not concrete low-level classes; this is why Services are constructor/method-injected rather than `new`'d inline.

### DRY / KISS / YAGNI

-   **DRY**: one unambiguous source of truth per piece of logic — don't copy-paste a calculation or validation rule across files.
-   **KISS**: the simplest solution that solves the actual problem wins. Don't reach for a generic factory/strategy pattern when a plain `if/else` or match suffices.
-   **YAGNI**: implement only what's needed today (Phase 1 / R1). Don't add params, config flags, or abstraction layers for hypothetical future requirements.

### Naming & Functions

-   Names are the documentation. Prefer intention-revealing names (`daysSinceCreation`) over abbreviations (`d`).
-   Keep functions small and single-purpose. A name containing "And" (`validateAndSaveUser`) is a signal to split it.
-   if variable is boolean, prefix should be is/was/were. exp isNeededForValidation instead of neededForValidation.

### Comments

-   **Comments explain WHY, not WHAT.** Don't comment on code that already describes itself (e.g. `// increment i`, `// save to database`). Only add a comment when it captures context the code can't express on its own — a non-obvious constraint, a workaround for a specific external bug/API quirk, or a reason a "simpler" alternative was rejected.
-   Don't comment to apologize for or narrate messy logic — refactor instead.
-   TODOs must carry an owner or ticket reference, e.g. `// TODO(username): ...`.
-   **Don't write documentation inside a function body.** A short one-line `//` is fine for a single non-obvious line, but a multi-line design/rationale explanation belongs in the PHPDoc block above the function signature, not scattered as inline comments inside it. If the explanation covers a cross-file flow, a decision history, or is genuinely long, put it in `docs/` (only when the user asks for a doc) and leave only a one-line pointer in the code.
-   **JS/Vue: prefer JSDoc over bare `//` for anything documenting a function, computed, or composable** — use a `/** ... */` block above the declaration (with `@param`/`@returns` when the signature isn't self-evident) instead of a long `//` explanation.

### Error Handling

-   Prefer exceptions over sentinel error codes/`false` returns to signal failure.
-   Keep error handling from obscuring the primary business logic (early returns / guard clauses over deep nesting).

### Boy Scout Rule

-   When touching a file for a bug fix or feature, clean up the immediate area you're already looking at (rename a confusing variable, fix a typo, split an oversized function nearby) — but don't let this justify unrelated drive-by refactors outside the scope of the task.

### Review & Change-Size Discipline

-   Keep changes small and focused on one thing; a PR/CL doing one coherent thing beats a large mixed one.
-   PR/commit descriptions should cover both the WHAT (summary) and the WHY (context/business reason) — imperative-mood titles ("Add X", not "Added X").
-   A reviewer's checklist: is the design appropriate, does it work as intended, could it be simpler, are there tests, are names clear, are comments necessary and clear.

### Testing Culture

-   **The Beyoncé Rule**: if you liked it, you should have put a test on it — an untested regression is the author's gap, not the breaker's fault.
-   Match test weight to what's being verified: fast pure-logic unit tests, feature tests against the real DB (per this repo's "no SQLite in tests" convention), and end-to-end tests reserved for critical user journeys. Most tests should be feature tests.
-   A flaky test (intermittently passes/fails) is worse than no test — fix it immediately or delete it; don't let it linger ignored.

### Hyrum's Law

-   Any observable behavior of a shipped response shape will eventually be depended on by some consumer, whether promised or not. Be deliberate about what an Inertia prop or JSON response exposes.

## Working with Claude Code

-   **Never commit automatically.** Only run `git commit` when the user explicitly asks for it in that turn — finishing an edit/fix is not implicit permission to commit.
-   **Always explain your changes.** Show the location of the changes, what you changed, and a breakdown of your logic if possible.
-   **No `Co-Authored-By` trailer.** Do not append a `Co-Authored-By: Claude ...` line (or any co-author trailer) to commit messages.
-   **Never run `migrate:fresh` (or anything that drops/rebuilds all tables) against the `.env` database (`mitra_manager`) or any store database, and never run tests with `APP_ENV` overridden away from `testing`.** The Tenancy feature tests use `RefreshDatabase` (runs `migrate:fresh` — drops every table), which is only safe against the isolated `mitra_manager_test` database set in `phpunit.xml`. If in doubt, don't override `APP_ENV` or the DB settings at all.
-   Run `vendor/bin/pint --dirty --format agent` and the narrowest relevant `php artisan test --compact` after every change.
-   **Do not run the full test suite after a code change** — it is slow (several minutes, real MySQL). Run only the tests related to what changed (the test files or `--filter` for that feature, plus any test that covers a class you touched). Run the full suite only when the user explicitly asks for it.
-   Docs in `docs/` are in Indonesian; keep them that way, and only create new documentation files when explicitly requested.

## Deployment

-   **Hosting**: Hostinger (plan TBD), single hosting for Laravel + built Vue assets (build with `npm run build` locally/CI; the result is static files served by Laravel).
-   **Databases**: created manually via hPanel (limits per plan; 1 user per DB) — the app never runs `CREATE DATABASE`. Register each new store DB's credentials on its `stores` row.
-   **Queue/scheduler**: no long-running worker assumed on shared hosting — use cron with `php artisan queue:work --stop-when-empty` and `schedule:run`.

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>

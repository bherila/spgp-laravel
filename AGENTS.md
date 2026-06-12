# AGENTS.md

For AI coding agents working in the SPGP Laravel codebase.

## Operating principle

Treat the request as the minimum useful outcome, not the ceiling. Make focused, coherent changes and include directly related low-risk fixes when they prevent churn.

Work efficiently: inspect sibling files first, parallelize independent reads/checks, run targeted validation during iteration, and broaden validation only when risk or shared code warrants it. Avoid unrelated refactors, dependency changes, production-only work, and formatting-only churn.

## Project shape

- **Backend**: Laravel 13, PHP 8.2+ runtime target, MySQL in production, SQLite in-memory for tests.
- **Frontend**: React 19 + TypeScript, Vite, Tailwind CSS v4, shadcn/Radix-style components.
- **Auth**: `bherila/auth-laravel`; follow existing middleware/user conventions.
- **Integrations**: Sentry, CSP, Brevo/Symfony mailer, file/storage services where present.
- **Package manager standard**: Composer for PHP, pnpm for JS. Do not use npm/npx in project scripts.
- **CI**: PR CI should run changed-area checks. Push-to-main deploy remains in `deploy.yml`.

## Read order

1. `AGENTS.md` — this operating contract and project gotchas.
2. `TESTING.AGENTS.md` — required validation for agent work.
3. `TESTING.md` — deeper testing and database-safety reference.
4. Domain code/docs only for touched areas.

## Commands

```bash
# Setup
composer install && pnpm install
cp .env.example .env && php artisan key:generate
# Do not run migrations unless explicitly requested; see Database Safety.

# Development
composer dev
pnpm run dev

# Frontend checks
pnpm run type-check
pnpm run lint
pnpm run test
pnpm run build

# Backend checks
./vendor/bin/pint --test
php artisan test
vendor/bin/paratest --processes=auto   # CI/deploy path when available
```

## Date and time rules

All dates are stored as **UTC** in the database. Laravel is configured with `timezone = UTC` in `config/app.php`. All models use the `SerializesDatesAsLocal` trait (`app/Traits/SerializesDatesAsLocal.php`) which serializes dates as ISO 8601 ATOM strings with timezone offset (e.g. `2026-04-18T14:30:00+00:00`), allowing the browser to interpret them correctly in the user's local timezone.

### Frontend helpers — `resources/js/lib/dateHelpers.ts`

Use these functions for **all** date display. Do not create new local `formatDate()` functions.

| Function | When to use | Example output |
|---|---|---|
| `formatDateOnly(str)` | `DATE` columns (no time): `assign_code_date`, promo code `start_date`/`expiration_date`, `passholder_birth_date` | `4/18/2026` |
| `formatDateTime(str)` | `DATETIME`/`TIMESTAMP` columns: deadlines, `created_at`, `last_login_at` | `Apr 18, 2026, 02:30 PM PDT` |
| `getCountdown(str, now)` | Countdown to a future datetime | `2d 4h 37m` or `null` if expired |
| `THREE_DAYS_MS` | Constant for urgent-deadline detection | `259200000` |

- **DATE-only columns** → `formatDateOnly` (uses UTC components; avoids the midnight-UTC off-by-one for negative-offset timezones)
- **DATETIME/TIMESTAMP columns** → `formatDateTime` (local timezone with 3-letter TZ abbreviation)
- **Never** use `toLocaleDateString()` or `toLocaleString()` directly in components
- **Never** use `new Date(str).toLocaleString()` without timezone name
- Every backend model with date/datetime columns must use `SerializesDatesAsLocal`.

## Architecture rules

- Entry points live under `resources/js/`; one `.tsx` file per page.
- Admin pages live under `resources/js/admin/`.
- Shared UI lives in `resources/js/components/` and `resources/js/components/ui/` (shadcn/ui).
- Shared utilities live in `resources/js/lib/`; path alias `@/` resolves to `resources/js/`.
- Controllers live under `app/Http/Controllers/`, admin controllers under `app/Http/Controllers/Admin/` when applicable.
- API routes live in `routes/api.php`; web routes live in `routes/web.php`.
- Blade should remain an initial page shell that mounts React roots.

## Critical rules

1. **Database safety**: never run `php artisan migrate` or `php artisan schema:dump` unless explicitly requested. When explicitly requested, use SQLite only: `php artisan migrate --database=sqlite --no-interaction`; `php artisan schema:dump --database=sqlite`; never use `--prune`.
2. **Tests use SQLite in-memory**. Do not change tests to use MySQL. If shell `DB_*` variables interfere, unset them or run the clean environment fallback from `TESTING.md`.
3. **Clipboard API**: `navigator.clipboard` exists only in secure contexts. Always guard it before use.
4. **Promo codes**: `PromoCodeRepository` stores codes by season and country (`USA` or `Canada`); `country IS NULL` is treated as USA; assignment sets `assign_code_date = now()`.
5. **Pass request status flow**: submitted → promo code assigned → email notification time set → redemption date set.
6. **Security/CSP**: do not weaken CSP, expose secrets, or add inline script patterns without checking existing policy.
7. **React/TypeScript**: prefer existing components/utilities, strict types, `interface` for props, and path imports via `@/` where configured.
8. **PR/commit text**: strip `[codex]` or other tool prefixes from PR titles and commit subjects.

## Context budget

Keep root guidance compact. Pull in only the specific component, route, controller, test, or doc needed for the touched behavior. Do not duplicate long framework-generated guidance in root instruction files.

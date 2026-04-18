# Agent / AI Assistant Guide

This file documents conventions, patterns, and gotchas that are non-obvious from reading the code alone. Read it before making changes.

## Commands

```bash
# Type-check (run before every commit)
pnpm type-check

# Lint + auto-fix
pnpm lint:fix

# PHP tests
php artisan test

# Build frontend
pnpm run build
```

## Date / Time Conventions

All dates are stored as **UTC** in the database. Laravel is configured with `timezone = UTC` in `config/app.php`. All models use the `SerializesDatesAsLocal` trait (`app/Traits/SerializesDatesAsLocal.php`) which serializes dates as ISO 8601 ATOM strings with timezone offset (e.g. `2026-04-18T14:30:00+00:00`), allowing the browser to interpret them correctly in the user's local timezone.

### Frontend helpers — `resources/js/lib/dateHelpers.ts`

Use these functions for **all** date display. Do not create new local `formatDate()` functions.

| Function | When to use | Example output |
|---|---|---|
| `formatDateOnly(str)` | `DATE` columns (no time): `assign_code_date`, promo code `start_date`/`expiration_date`, `passholder_birth_date` | `4/18/2026` |
| `formatDateTime(str)` | `DATETIME`/`TIMESTAMP` columns: deadlines, `created_at`, `last_login_at` | `Apr 18, 2026, 02:30 PM PDT` |
| `getCountdown(str, now)` | Countdown to a future datetime | `2d 4h 37m` or `null` if expired |
| `THREE_DAYS_MS` | Constant for urgent-deadline detection | `259200000` |

Rules:
- **DATE-only columns** → `formatDateOnly` (uses UTC components; avoids the midnight-UTC off-by-one for negative-offset timezones)
- **DATETIME/TIMESTAMP columns** → `formatDateTime` (local timezone with 3-letter TZ abbreviation)
- **Never** use `toLocaleDateString()` or `toLocaleString()` directly in components
- **Never** use `new Date(str).toLocaleString()` without timezone name

### Backend models

Every model must use `SerializesDatesAsLocal`. If you add a new model with date/datetime columns, add `use SerializesDatesAsLocal;` and the corresponding `use App\Traits\SerializesDatesAsLocal;` import.

## Frontend Architecture

- Entry points are in `resources/js/` — one `.tsx` file per page (e.g. `dashboard.tsx`, `request/index.tsx`)
- Admin pages live in `resources/js/admin/`
- Shared UI components: `resources/js/components/` and `resources/js/components/ui/` (shadcn/ui)
- Shared utilities: `resources/js/lib/` (`dateHelpers.ts`, `passRequestHelpers.ts`, `utils.ts`)
- Path alias `@/` resolves to `resources/js/`

## Backend Architecture

- Controllers under `app/Http/Controllers/` (admin controllers in `/Admin/` subdirectory)
- Models under `app/Models/`
- API routes registered in `routes/api.php`; web routes in `routes/web.php`
- All API responses return JSON; Blade is only used for initial page shells that mount React roots

## Clipboard API

`navigator.clipboard` is only available in secure contexts (HTTPS). Always guard it:

```typescript
if (navigator.clipboard) {
  navigator.clipboard.writeText(text);
}
```

## Country / Promo Code Assignment

- `PromoCodeRepository` stores codes per season and country (`USA` or `Canada`)
- Auto-assign treats pass requests with `country IS NULL` as USA
- The `PromoCodeRepositoryController::autoAssign()` sets `assign_code_date = now()` on each assigned `PassRequest`

## Pass Request Status Flow

```
submitted → promo_code assigned → email_notify_time set → redemption_date set
```

Fields:
- `promo_code`: null until assigned
- `assign_code_date`: DATE (no time) set when code is assigned
- `email_notify_time`: DATETIME set when notification email is sent
- `redemption_date`: DATE set when user redeems the code

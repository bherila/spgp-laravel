# AGENTS.md

For AI coding agents working in `bherila/spgp-laravel`.

## Operating principle

Treat the user's request as the minimum useful outcome, not the ceiling. Solve the underlying problem well, including small adjacent fixes when they are clearly related and low-risk.

Work efficiently: inspect neighboring files before inventing patterns, run targeted validations during iteration, and broaden only when risk or shared code warrants it. Keep changes focused; avoid unrelated refactors, dependency churn, production builds, or broad test runs unless the task requires them.

## Project shape

- Domain: Season Pass Group Purchase, a Laravel application for coordinating ski pass group purchases, promo-code assignment, season Q&A, email notifications, and admin operations.
- Stack: Laravel 13, PHP 8.2+, React 19, TypeScript, Tailwind CSS v4, shadcn/Radix UI, Vite, MySQL/SQLite, Sentry when configured.
- Package manager: `pnpm` (`packageManager` is `pnpm@11.5.0`). Do not introduce npm/yarn workflows unless the task is specifically about package tooling.
- CI/deploy: pushing `main` runs tests and then deploys to production. Treat the local/pre-push gate as higher stakes than a PR-only repo.

## Commands

```bash
# Install
composer install --no-interaction --prefer-dist
pnpm install --frozen-lockfile

# Development
php artisan serve
pnpm run dev

# Frontend checks
pnpm run build
pnpm run type-check
pnpm run lint
pnpm run test

# Backend checks
composer test
php artisan test
vendor/bin/paratest --processes=auto
php -l path/to/file.php   # modified PHP files only
```

`composer setup` and `composer dev` still contain legacy npm/npx commands. Prefer explicit `pnpm` commands unless the task is to modernize those scripts.

## Validation strategy

Use a two-layer loop:

1. **During iteration**: run the smallest command that proves the touched behavior. Examples: a specific Jest file, `php artisan test --filter=...`, `php -l` for changed PHP, or a focused browser/manual check when UI behavior is not test-covered.
2. **Before push / handoff**: run the affected-stack gate. For changes that could reach `main`, match the deploy workflow's test job:
   - `pnpm run build`
   - `pnpm run type-check`
   - `pnpm run lint`
   - `pnpm run test`
   - `vendor/bin/paratest --processes=auto` when dependencies are installed; otherwise `composer test` / `php artisan test` with the limitation called out.

If a failure is clearly pre-existing and unrelated, document the command and concise output summary. Do not leave a red main-bound branch without calling that out explicitly.

## Laravel / PHP conventions

- Use Laravel idioms first: Form Requests for non-trivial validation, policies/gates/middleware for authorization, services for business workflows, and Eloquent relationships over manual joins unless the query is genuinely complex.
- Add explicit return types to new PHP methods/functions.
- Use constructor property promotion when adding injected dependencies.
- Controllers should orchestrate request/response flow; keep season, pass request, promo-code, email, and Q&A business logic in models/services/actions consistent with existing code.
- Use Laravel Boost `search-docs` before changing Laravel ecosystem behavior when the tool is available.

## Database safety

- Do **not** run production-like migrations, `php artisan migrate --force`, or schema dumps unless the user explicitly asks.
- If the user asks for a local migration check, prefer `php artisan migrate --database=sqlite --no-interaction`; the README explicitly warns to pass `--database=sqlite` if `.env` may point at production MySQL.
- Never delete existing migration files. Keep index and foreign-key names short and explicit when names may approach MySQL's identifier limit.

## Date / time conventions

All database dates are stored as UTC. Models with date/datetime columns must use `SerializesDatesAsLocal` from `app/Traits/SerializesDatesAsLocal.php`.

Frontend display must use `resources/js/lib/dateHelpers.ts`:

- `formatDateOnly(str)` for `DATE` columns such as `assign_code_date`, promo code dates, and `passholder_birth_date`.
- `formatDateTime(str)` for `DATETIME` / `TIMESTAMP` columns such as deadlines, `created_at`, and `last_login_at`.
- `getCountdown(str, now)` for deadline countdowns.

Never use `toLocaleDateString()`, `toLocaleString()`, or `new Date(str).toLocaleString()` directly in components.

## SPGP domain rules

- Preserve the pass request status flow: `submitted -> promo_code assigned -> email_notify_time set -> redemption_date set`.
- `PromoCodeRepository` stores codes per season and country (`USA` or `Canada`). Auto-assign treats `country IS NULL` as `USA`.
- `assign_code_date` and `redemption_date` are `DATE` fields; `email_notify_time` is a `DATETIME` field.
- Invite codes and promo codes are sensitive operational data. Do not print real values in PRs, issues, commit messages, or logs.
- Email-related changes must preserve email logging and avoid duplicate sends on retries or repeated admin actions.
- Season deadlines drive dashboard urgency and user expectations; verify countdown/date changes against both admin and user-facing views.

## React / TypeScript conventions

- Entry points live in `resources/js/`; admin pages live in `resources/js/admin/`.
- Use existing shared components in `resources/js/components/` and `resources/js/components/ui/` before adding new UI primitives.
- Use shared utilities in `resources/js/lib/`, especially `dateHelpers.ts`, `passRequestHelpers.ts`, and `utils.ts`.
- The `@/` alias resolves to `resources/js/`.
- Guard Clipboard API use with `if (navigator.clipboard)` because it requires a secure context.
- Prefer `interface` for component props, function declarations for React components, and named exports for shared utilities/components.
- Use `currency.js` for any money arithmetic involving pass prices, discounts, totals, or refunds.

## Privacy and repo hygiene

- Do not include real names, email addresses, passholder birth dates, invite codes, promo codes, Sentry DSNs, access tokens, or production payloads in PR titles/bodies, issue bodies, commit subjects, branch names, or logs.
- Use database references such as `users#7`, `seasons#3`, `pass_requests#22`, or `promo_code_repository#9` when a durable reference is needed.
- Strip `[codex]`, `[claude]`, or other tool tags from PR titles and commit subjects.
- Use the GitHub CLI for local GitHub operations when available; prefer fully qualified refs such as `bherila/spgp-laravel#123`.

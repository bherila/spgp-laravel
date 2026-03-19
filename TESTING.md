# Testing Guide

## Running All Validations

Run the following commands in order to execute every validation step. The frontend **must be built before running phpunit** because `BladeViewTest` loads each blade view with the real Vite manifest to catch missing asset entries.

```bash
# 1. TypeScript type-checking
pnpm run type-check

# 2. ESLint (frontend linting)
pnpm run lint

# 3. Build the Vite frontend (required before phpunit)
pnpm run build

# 4. Jest unit tests (frontend)
pnpm run test

# 5. PHPUnit (backend – must run after pnpm run build)
vendor/bin/phpunit --configuration phpunit.xml
```

## PHP Tests (PHPUnit)

To run the backend tests, use the following command:

```bash
php artisan test
```

> **Important:** Some tests in `BladeViewTest` render blade views using the real Vite manifest. Run `pnpm run build` before `phpunit` so the manifest is available; otherwise those tests will fail with a `ViteException`.

### Database Safety
Tests are configured to use an **in-memory SQLite database** to prevent accidental modifications to your local development or production database. This is enforced by `Tests\SafeTestCase`.

### Common Issues

#### 1. "SAFETY ERROR: Tests must use SQLite, but 'mysql' connection is active"
If you see this error, it's likely because your shell has `DB_CONNECTION` exported as `mysql`, which overrides the settings in `phpunit.xml`.

**Solution:** Explicitly set the environment variables when running tests. If your shell variables are still overriding the configuration, use `env -i` to run the test in a clean environment:

```bash
# Basic override
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test

# Robust clean-environment override (recommended if above fails)
env -i PATH="$PATH" DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
```

#### 2. CSRF Failures (419 Status Code)
If tests fail with a 419 error, ensure you are using the correct middleware exclusion in your test's `setUp()` method if you need to bypass it:

```php
$this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
```

*Note: In Laravel 11/12, the class name is `ValidateCsrfToken` (or `VerifyCsrfToken` depending on exact version/config).*

#### 3. Session Issues (500 Error: "Session store not set on request")
This often happens if you call `$this->withoutMiddleware()` without arguments, which disables the session middleware. Always prefer targeted middleware exclusion or use the `RefreshDatabase` trait.

#### 4. ViteException ("Unable to locate file in Vite manifest")
This error means a blade view references an asset that is either missing from `vite.config.ts` or the frontend has not been built yet.

- Ensure the asset is listed in the `input` array in `vite.config.ts`.
- Run `pnpm run build` before executing phpunit.

## Frontend Tests (Jest)

To run the frontend unit tests:

```bash
pnpm run test
```

All TypeScript/React tests should be located in the `/tests-ts` directory.

## TypeScript Type-Checking

```bash
pnpm run type-check
```

## Linting

To run the linter:

```bash
pnpm run lint
```

To automatically fix fixable linting errors:

```bash
pnpm run lint:fix
```

# Testing Guide

## PHP Tests (PHPUnit)

To run the backend tests, use the following command:

```bash
php artisan test
```

### Database Safety
Tests are configured to use an **in-memory SQLite database** to prevent accidental modifications to your local development or production database. This is enforced by `Tests\SafeTestCase`.

### Common Issues

#### 1. "SAFETY ERROR: Tests must use SQLite, but 'mysql' connection is active"
If you see this error, it's likely because your shell has `DB_CONNECTION` exported as `mysql`, which overrides the settings in `phpunit.xml`.

**Solution:** Explicitly set the environment variables when running tests:

```bash
DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
```

#### 2. CSRF Failures (419 Status Code)
If tests fail with a 419 error, ensure you are using the correct middleware exclusion in your test's `setUp()` method if you need to bypass it:

```php
$this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
```

*Note: In Laravel 11/12, the class name is `ValidateCsrfToken` (or `VerifyCsrfToken` depending on exact version/config).*

#### 3. Session Issues (500 Error: "Session store not set on request")
This often happens if you call `$this->withoutMiddleware()` without arguments, which disables the session middleware. Always prefer targeted middleware exclusion or use the `RefreshDatabase` trait.

## Frontend Tests (Jest)

To run the frontend unit tests:

```bash
pnpm test
```

All TypeScript/React tests should be located in the `/tests-ts` directory.

## Linting

To run the linter:

```bash
pnpm lint
```

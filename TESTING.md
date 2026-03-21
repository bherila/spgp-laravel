# Testing Guide

All steps must pass. Order matters.

---

## Full Validation Pipeline

```bash
pnpm run type-check
pnpm run lint
pnpm run build
pnpm run test
./vendor/bin/pint --test
php artisan test
```

---

## Frontend

### TypeScript

`pnpm run type-check`

### Linting

* `pnpm run lint`
* Autofix is supported if there are errors: `pnpm run lint:fix`

### Jest
* `pnpm run test`

---

## Backend

### Build (required to build Vite manifest before PHPunit tests)

`pnpm run build`


### PHPUnit

`php artisan test`

### PHP Linting

* Validation: `vendor/bin/pint --test`
* Autofix: `vendor/bin/pint`

---

## Database Safety

All backend tests use **SQLite in-memory**, enforced by `Tests\SafeTestCase`.

Required test env:

```
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
APP_ENV=testing
```

If shell overrides DB settings:

```bash
unset DB_CONNECTION DB_DATABASE DB_HOST DB_PORT DB_USERNAME DB_PASSWORD
php artisan test
```

Clean environment fallback:

```bash
env -i PATH="$PATH" DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test
```

---

## Common Failures

### Wrong DB Connection
Fix by overriding env (see above).

### CSRF 419 Errors
```php
$this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
```

### Session Errors
Avoid blanket `$this->withoutMiddleware()`.

### Vite Manifest Errors
Ensure asset exists in `vite.config.ts` input and run:

```bash
pnpm run build
```

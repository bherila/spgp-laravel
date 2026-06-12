# Testing — Agent Requirements

These are the routine checks for AI coding agents. `TESTING.md` remains the deeper reference.

## Full local gate

Because the current GitHub workflow runs on `main` pushes, run the relevant local gate before pushing or merging:

```bash
pnpm run type-check
pnpm run lint
pnpm run build
pnpm run test
./vendor/bin/pint --test
php artisan test
```

If Paratest is available and you need the CI/deploy-equivalent backend path, use:

```bash
vendor/bin/paratest --processes=auto
```

## Frontend changes

Run:

```bash
pnpm run type-check
pnpm run lint
pnpm run test
```

Run `pnpm run build` when Vite entries, Blade shells, asset inputs, or production rendering changed.

## Backend changes

Run:

```bash
./vendor/bin/pint --test
php artisan test
```

During iteration, targeted tests are acceptable. Before finalizing, broaden to the full relevant backend gate unless unrelated pre-existing failures block it; report any such failure explicitly.

## Database safety

Never run migrations or schema dumps unless the user explicitly requests it. When explicitly requested:

```bash
php artisan migrate --database=sqlite --no-interaction
php artisan schema:dump --database=sqlite
```

Never use `--prune`. Tests must use SQLite in-memory and must never run against MySQL.

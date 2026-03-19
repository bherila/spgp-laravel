<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * ViteTestCase - A base test class that enforces SQLite usage but keeps Vite enabled.
 *
 * Unlike SafeTestCase, this class does NOT call withoutVite(). This means
 * the real Vite manifest is used when rendering blade views, so tests will fail
 * with a ViteException if an asset is missing from the manifest (e.g. because
 * it was not added to vite.config.ts).
 *
 * Requires the Vite manifest to be built before running (pnpm run build).
 * The CI pipeline runs pnpm run build before phpunit to satisfy this requirement.
 *
 * Usage:
 *   - Blade view integration tests should extend this class
 *   - Use the RefreshDatabase trait to get a clean database per test
 */
abstract class ViteTestCase extends BaseTestCase
{
    /**
     * Boot the testing helper traits and verify database safety.
     */
    protected function setUpTraits(): array
    {
        $this->assertDatabaseIsSafeSqlite();

        return parent::setUpTraits();
    }

    /**
     * Assert that the database connection is SQLite in-memory.
     *
     * @throws RuntimeException if not using SQLite in-memory database
     */
    protected function assertDatabaseIsSafeSqlite(): void
    {
        $connection = DB::connection();
        $driverName = $connection->getDriverName();
        $database = $connection->getDatabaseName();

        if ($driverName !== 'sqlite') {
            throw new RuntimeException(
                "SAFETY ERROR: Tests must use SQLite, but '{$driverName}' connection is active. " .
                "This could lead to accidentally modifying a production database!\n\n" .
                "Ensure phpunit.xml contains:\n" .
                "  <env name=\"DB_CONNECTION\" value=\"sqlite\"/>\n" .
                "  <env name=\"DB_DATABASE\" value=\":memory:\"/>\n\n" .
                "And run tests with: composer test (or php artisan test)"
            );
        }

        if ($database !== ':memory:' && basename($database) !== 'database.sqlite') {
            throw new RuntimeException(
                "SAFETY ERROR: Tests must use in-memory SQLite (or at least a sqlite file named database.sqlite), but database is '{$database}'.\n\n" .
                "Using a file-based database could persist test data unexpectedly.\n" .
                "Ensure phpunit.xml contains:\n" .
                "  <env name=\"DB_DATABASE\" value=\":memory:\"/>"
            );
        }
    }
}

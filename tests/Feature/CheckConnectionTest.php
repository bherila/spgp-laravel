<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class CheckConnectionTest extends BaseTestCase
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }

    public function test_connection_details(): void
    {
        $driver = DB::connection()->getDriverName();
        $database = DB::connection()->getDatabaseName();
        $default = Config::get('database.default');
        $env_helper = env('DB_CONNECTION');
        $getenv = getenv('DB_CONNECTION');
        $env_global = $_ENV['DB_CONNECTION'] ?? 'not set';
        $server_global = $_SERVER['DB_CONNECTION'] ?? 'not set';
        
        echo "\n--- Database Connection Details ---\n";
        echo "Driver (DB::connection): $driver\n";
        echo "Database (DB::connection): $database\n";
        echo "Default Config (database.default): $default\n";
        echo "env() helper: $env_helper\n";
        echo "getenv(): $getenv\n";
        echo "\$_ENV['DB_CONNECTION']: $env_global\n";
        echo "\$_SERVER['DB_CONNECTION']: $server_global\n";
        echo "-----------------------------------\n";
        
        $this->assertTrue(true);
    }
}

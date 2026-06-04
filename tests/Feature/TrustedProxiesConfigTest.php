<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class TrustedProxiesConfigTest extends TestCase
{
    private string $configCachePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configCachePath = sys_get_temp_dir().'/spgp-config-cache-'.bin2hex(random_bytes(8)).'.php';
    }

    protected function tearDown(): void
    {
        if (isset($this->configCachePath) && file_exists($this->configCachePath)) {
            unlink($this->configCachePath);
        }

        parent::tearDown();
    }

    public function test_trusted_proxies_are_read_from_cached_config(): void
    {
        try {
            $this->runProcess([PHP_BINARY, 'artisan', 'config:cache'], [
                'TRUSTED_PROXIES' => '10.0.0.1, 10.0.0.2',
            ]);

            $clientIp = $this->runProcess([PHP_BINARY, '-r', <<<'PHP'
require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create('/up', 'GET', [], [], [], [
    'REMOTE_ADDR' => '10.0.0.2',
    'HTTP_X_FORWARDED_FOR' => '198.51.100.23',
    'HTTP_X_FORWARDED_HOST' => 'example.test',
    'HTTP_X_FORWARDED_PROTO' => 'https',
]);

$response = $kernel->handle($request);

echo $request->ip();

$kernel->terminate($request, $response);
PHP
            ], [
                'TRUSTED_PROXIES' => false,
            ]);

            $this->assertSame('198.51.100.23', trim($clientIp));
        } finally {
            $this->runProcess([PHP_BINARY, 'artisan', 'config:clear']);
        }
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string|false>  $env
     */
    private function runProcess(array $command, array $env = []): string
    {
        $process = new Process(
            $command,
            dirname(__DIR__, 2),
            array_replace($this->baseProcessEnvironment(), $env),
        );
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue(
            $process->isSuccessful(),
            sprintf(
                "Command failed: %s\nExit code: %d\nSTDOUT:\n%s\nSTDERR:\n%s",
                $process->getCommandLine(),
                $process->getExitCode(),
                $process->getOutput(),
                $process->getErrorOutput(),
            ),
        );

        return $process->getOutput();
    }

    /**
     * @return array<string, string>
     */
    private function baseProcessEnvironment(): array
    {
        return [
            'APP_CONFIG_CACHE' => $this->configCachePath,
            'APP_ENV' => 'testing',
            'APP_KEY' => 'base64:2fl+K9kGZYSY6ytAtpKr6EonZcgOkInGo9pWun/clVs=',
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'CACHE_DRIVER' => 'array',
            'CACHE_STORE' => 'array',
            'DB_CONNECTION' => 'sqlite',
            'DB_DATABASE' => ':memory:',
            'BHERILA_AUTH_AUDIT_DRIVER' => 'database',
            'MAIL_MAILER' => 'array',
            'PULSE_ENABLED' => 'false',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
            'TELESCOPE_ENABLED' => 'false',
            'NIGHTWATCH_ENABLED' => 'false',
        ];
    }
}

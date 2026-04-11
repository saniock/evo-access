<?php

namespace Saniock\EvoAccess\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Saniock\EvoAccess\EvoAccessServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Simulate an authenticated EVO manager session so BaseController
        // gating doesn't abort every feature test. Individual tests that
        // need to assert the unauthenticated path can clear these keys.
        $_SESSION['mgrValidated'] = 1;
        $_SESSION['mgrInternalKey'] = 1;
    }

    protected function tearDown(): void
    {
        unset($_SESSION['mgrValidated'], $_SESSION['mgrInternalKey']);
        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            EvoAccessServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');
    }
}

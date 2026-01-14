<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SSIPG\Auditable\AuditableServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auditable.enabled', true);

        $app['config']->set('auditable.environments', ['testing']);

        $app['config']->set('auditable.channel', 'audit');

        $app['config']->set('logging.channels.audit', [
            'driver' => 'single',
            'level'  => 'info',
            'path'   => storage_path('logs/audit.log'),
        ]);
    }

    protected function getPackageProviders($app): array
    {
        return [
            AuditableServiceProvider::class,
        ];
    }
}

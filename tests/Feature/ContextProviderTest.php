<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Feature;

use SSIPG\Auditable\Tests\Fixtures\ContextProviders\EmptyContextProvider;
use SSIPG\Auditable\Tests\Fixtures\ContextProviders\SecondContextProvider;
use SSIPG\Auditable\Tests\Fixtures\ContextProviders\TestContextProvider;
use SSIPG\Auditable\Tests\Fixtures\Formatters\TestFormatter;
use SSIPG\Auditable\Tests\Fixtures\Models\TestModel;
use SSIPG\Auditable\Tests\TestCase;

class ContextProviderTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logFile = storage_path('logs/test-audit.log');

        // Clean up any existing test log
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        // Configure a test log channel
        config([
            'logging.channels.audit' => [
                'driver' => 'single',
                'path'   => $this->logFile,
                'level'  => 'info',
            ],
        ]);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }

        parent::tearDown();
    }

    public function test_custom_context_added(): void
    {
        config([
            'auditable.context_providers' => [
                TestContextProvider::class,
            ],
        ]);

        TestModel::create(['name' => 'John']);

        $this->assertLogContains('project_id');
        $this->assertLogContains('request_id');
        $this->assertLogContains('test-request-123');
    }

    public function test_custom_formatter(): void
    {
        config(['auditable.formatter' => TestFormatter::class]);

        TestModel::create(['name' => 'John']);

        // TestFormatter prepends "CUSTOM:" to the output
        $this->assertLogContains('CUSTOM:');
    }

    public function test_empty_provider(): void
    {
        config([
            'auditable.context_providers' => [
                TestContextProvider::class,
                EmptyContextProvider::class,
            ],
        ]);

        TestModel::create(['name' => 'John']);

        // Data from TestContextProvider should still be present
        $this->assertLogContains('project_id');
        $this->assertLogContains('request_id');

        // Core payload should still be present
        $this->assertLogContains('created');
        $this->assertLogContains('John');
    }

    public function test_invalid_configuration(): void
    {
        // Configure a nonexistent log channel
        config(['auditable.channel' => 'nonexistent_channel']);

        // Model creation should still succeed despite audit logging failure
        TestModel::create(['name' => 'John']);

        $this->assertDatabaseHas('test_models', ['name' => 'John']);
    }

    public function test_multiple_providers(): void
    {
        config([
            'auditable.context_providers' => [
                TestContextProvider::class,
                SecondContextProvider::class,
            ],
        ]);

        TestModel::create(['name' => 'John']);

        // From TestContextProvider
        $this->assertLogContains('project_id');
        $this->assertLogContains('request_id');

        // From SecondContextProvider
        $this->assertLogContains('environment');
        $this->assertLogContains('host');
    }

    private function assertLogContains(string $needle): void
    {
        $this->assertFileExists($this->logFile, 'Log file was not created');

        $contents = file_get_contents($this->logFile);
        $this->assertIsString($contents);

        $this->assertStringContainsString(
            $needle,
            $contents,
            "Log file does not contain expected string: {$needle}"
        );
    }
}

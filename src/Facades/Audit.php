<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Facades;

use Illuminate\Support\Facades\Facade;
use SSIPG\Auditable\AuditLogger;
use SSIPG\Auditable\Testing\AuditFake;

/**
 * @method static void assertLogged(\SSIPG\Auditable\Enums\AuditAction $action, callable|null $callback = null)
 * @method static void assertNotLogged(\SSIPG\Auditable\Enums\AuditAction $action, callable|null $callback = null)
 * @method static void assertLoggedCount(int $count)
 * @method static void assertNothingLogged()
 * @method static list<array<string, mixed>> logged(\SSIPG\Auditable\Enums\AuditAction|null $action = null)
 *
 * @see \SSIPG\Auditable\AuditLogger
 * @see \SSIPG\Auditable\Testing\AuditFake
 */
class Audit extends Facade
{
    public static function fake(): AuditFake
    {
        config([
            'auditable.enabled'      => true,
            'auditable.environments' => ['testing'],
        ]);

        static::swap($fake = new AuditFake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}

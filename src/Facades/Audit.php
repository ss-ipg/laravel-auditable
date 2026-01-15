<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Facades;

use Illuminate\Support\Facades\Facade;
use SSIPG\Auditable\AuditLogger;
use SSIPG\Auditable\Testing\AuditFake;

/**
 * @method static array<string, mixed>[] all()
 * @method static void assertLogged(\SSIPG\Auditable\Enums\AuditAction $action, callable|null $callback = null)
 * @method static void assertLoggedCount(int $count)
 * @method static void assertNotLogged(\SSIPG\Auditable\Enums\AuditAction $action, callable|null $callback = null)
 * @method static void assertNothingLogged()
 * @method static void clear()
 * @method static array<string, mixed>[] logged(\SSIPG\Auditable\Enums\AuditAction|null $action = null, string|null $model = null)
 *
 * @see \SSIPG\Auditable\AuditLogger
 * @see \SSIPG\Auditable\Testing\AuditFake
 */
class Audit extends Facade
{
    /** @param  class-string<\Illuminate\Database\Eloquent\Model>|list<class-string<\Illuminate\Database\Eloquent\Model>>|null  $models */
    public static function fake(string|array|null $models = null): AuditFake
    {
        config([
            'auditable.enabled'      => true,
            'auditable.environments' => ['testing'],
        ]);

        $fakeModels = match (true) {
            $models === null  => null,
            is_array($models) => $models,
            default           => [$models],
        };

        static::swap($fake = new AuditFake($fakeModels));

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return AuditLogger::class;
    }
}

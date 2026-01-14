<?php

declare(strict_types=1);

namespace SSIPG\Auditable;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/auditable.php' => config_path('auditable.php'),
            ], 'auditable-config');
        }

        Event::listen(
            events: 'eloquent.created: *',
            listener: static fn (...$args) => app(AuditLogger::class)->handleCreated(...$args),
        );

        Event::listen(
            events: 'eloquent.updated: *',
            listener: static fn (...$args) => app(AuditLogger::class)->handleUpdated(...$args),
        );

        Event::listen(
            events: 'eloquent.deleted: *',
            listener: static fn (...$args) => app(AuditLogger::class)->handleDeleted(...$args),
        );

        Event::listen(
            events: 'eloquent.restored: *',
            listener: static fn (...$args) => app(AuditLogger::class)->handleRestored(...$args),
        );
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/auditable.php', 'auditable');

        $this->app->singleton(AuditLogger::class);
    }
}

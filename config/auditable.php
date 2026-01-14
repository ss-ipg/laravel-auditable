<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Enable Audit Logging
    |--------------------------------------------------------------------------
    |
    | Controls whether audit logging is active. Use this to enable or disable
    | logging at runtime via your .env file without code changes.
    |
    | Both this AND the environments check must pass for logging to occur.
    |
    */
    'enabled' => env('AUDITABLE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Allowlist of environments where audit logging is permitted. Even if
    | 'enabled' is true, logging will only occur in these environments.
    |
    | Set to ['*'] to allow all environments.
    |
    */
    'environments' => ['local', 'production', 'staging'],

    /*
    |--------------------------------------------------------------------------
    | Log Channel
    |--------------------------------------------------------------------------
    |
    | The logging channel to use. You must define this channel in your
    | config/logging.php file.
    |
    | Example channel configuration:
    |
    | 'audit' => [
    |     'driver' => 'daily',
    |     'path' => storage_path('logs/audit.log'),
    |     'level' => 'info',
    |     'days' => 14,
    | ],
    |
    */
    'channel' => 'audit',

    /*
    |--------------------------------------------------------------------------
    | Context Providers
    |--------------------------------------------------------------------------
    |
    | Context providers allow you to add custom key/value pairs to every audit
    | log entry. Each provider must implement AuditContextProvider interface.
    | Providers are called in order and their results are merged.
    |
    | Example uses: impersonation detection, team ID, request tracing, etc.
    |
    */
    'context_providers' => [
        // App\Audit\ImpersonationContextProvider::class,
        // App\Audit\TeamCustomContextProvider::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Formatter
    |--------------------------------------------------------------------------
    |
    | The formatter determines how audit log entries are serialized before
    | being written to the log channel. Must implement AuditFormatter interface.
    |
    | Default: JsonFormatter (clean JSON output)
    |
    */
    'formatter' => SSIPG\Auditable\Formatters\JsonFormatter::class,
];

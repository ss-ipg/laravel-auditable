<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Contracts;

interface AuditFormatter
{
    /**
     * Format the audit payload for logging.
     *
     * @param  array<string, mixed>  $payload
     */
    public function format(array $payload): string;
}

<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Formatters;

use JsonException;
use SSIPG\Auditable\Contracts\AuditFormatter;

class JsonFormatter implements AuditFormatter
{
    /**
     * Format the audit payload as clean JSON.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function format(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

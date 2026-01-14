<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Formatters;

use SSIPG\Auditable\Contracts\AuditFormatter;

class TestFormatter implements AuditFormatter
{
    public function format(array $payload): string
    {
        return 'CUSTOM:'.json_encode($payload, JSON_THROW_ON_ERROR);
    }
}

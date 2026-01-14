<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Contracts;

use Illuminate\Database\Eloquent\Model;
use SSIPG\Auditable\Enums\AuditAction;

interface AuditContextProvider
{
    /**
     * Get additional context to include in the audit log entry.
     *
     * @return array<string, mixed>
     */
    public function getContext(Model $model, AuditAction $action): array;
}

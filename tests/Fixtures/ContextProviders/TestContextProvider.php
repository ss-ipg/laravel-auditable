<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\ContextProviders;

use Illuminate\Database\Eloquent\Model;
use SSIPG\Auditable\Contracts\AuditContextProvider;
use SSIPG\Auditable\Enums\AuditAction;

class TestContextProvider implements AuditContextProvider
{
    public function getContext(Model $model, AuditAction $action): array
    {
        return [
            'project_id' => 42,
            'request_id' => 'test-request-123',
        ];
    }
}

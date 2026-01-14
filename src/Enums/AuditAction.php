<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Enums;

enum AuditAction: string
{
    case Created = 'created';

    case Updated = 'updated';

    case Deleted = 'deleted';

    case SoftDeleted = 'soft_deleted';

    case Restored = 'restored';
}

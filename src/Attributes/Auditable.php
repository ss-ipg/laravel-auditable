<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Attributes;

use Attribute;
use SSIPG\Auditable\Enums\AuditAction;

#[Attribute(Attribute::TARGET_CLASS)]
class Auditable
{
    /**
     * @param  list<string>|null  $columns  Only audit these columns. null = all columns.
     * @param  list<string>  $exclude  Exclude these columns from auditing.
     * @param  list<string>  $redact  Log that column changed, but show [REDACTED] instead of values.
     * @param  list<AuditAction>  $events  Which events to audit. Default: all events.
     * @param  bool  $withOriginal  Include original values in update logs.
     */
    public function __construct(
        public readonly ?array $columns = null,
        public readonly array $exclude = [],
        public readonly array $redact = [],
        public readonly array $events = [
            AuditAction::Created,
            AuditAction::Updated,
            AuditAction::Deleted,
            AuditAction::SoftDeleted,
            AuditAction::Restored,
        ],
        public readonly bool $withOriginal = true,
    ) {}
}

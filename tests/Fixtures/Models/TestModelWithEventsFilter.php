<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use SSIPG\Auditable\Attributes\Auditable;
use SSIPG\Auditable\Enums\AuditAction;

#[Auditable(events: [AuditAction::Created, AuditAction::Deleted])]
class TestModelWithEventsFilter extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $table = 'test_models';
}

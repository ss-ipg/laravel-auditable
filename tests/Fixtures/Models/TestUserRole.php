<?php

declare(strict_types=1);

namespace SSIPG\Auditable\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use SSIPG\Auditable\Attributes\Auditable;

#[Auditable]
class TestUserRole extends Pivot
{
    public $timestamps = false;

    protected $table = 'test_user_roles';
}
